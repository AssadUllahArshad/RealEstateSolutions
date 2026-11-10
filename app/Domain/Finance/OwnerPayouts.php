<?php

namespace App\Domain\Finance;

use App\Models\FinancialAccount;
use App\Models\OwnerPayout;
use App\Models\PlotOwner;
use App\Models\Society;
use App\Models\User;
use App\Rules\PositiveDecimal;
use App\Support\BusinessClock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OwnerPayouts
{
    /** @return numeric-string */
    public function payable(int $ownerId, bool $lock = false): string
    {
        $events = DB::table('owner_entitlement_events')->where('plot_owner_id', $ownerId);
        $payouts = OwnerPayout::where('plot_owner_id', $ownerId)->where('state', 'approved');
        $entitled = ($lock ? $events->lockForUpdate() : $events)->sum('entitlement_amount');
        $paid = ($lock ? $payouts->lockForUpdate() : $payouts)->sum('amount');

        return bcsub($this->numeric($entitled), $this->numeric($paid), 2);
    }

    /** @param array<string,mixed> $input */
    public function prepare(User $actor, array $input): OwnerPayout
    {
        $data = Validator::make($input, ['plot_owner_id' => ['required', 'integer', 'exists:plot_owners,id'], 'financial_account_id' => ['required', 'integer', 'exists:financial_accounts,id'], 'amount' => ['required', new PositiveDecimal], 'business_date' => ['required', 'date_format:Y-m-d'], 'reference' => ['nullable', 'string', 'max:500'], 'verification_note' => ['required', 'string', 'min:10', 'max:2000'], 'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted']])->validate();
        $data['amount'] = bcadd($this->numeric($data['amount']), '0', 2);
        if ($data['business_date'] !== app(BusinessClock::class)->today()) {
            throw ValidationException::withMessages(['business_date' => 'Use the current Karachi business date.']);
        }
        $payload = json_encode(array_diff_key($data, ['command_key' => true, 'confirmed' => true]), JSON_THROW_ON_ERROR);
        $hashes = array_map(fn ($key) => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]);

        return DB::transaction(function () use ($actor, $data, $hashes) {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($fresh)->authorize('finance.owner-payouts.prepare');
            abort_unless($fresh->hasRole('accountant'), 403);
            Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $prior = OwnerPayout::where('prepared_by', $fresh->id)->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This payout command was reused with different details.');
                }

                return $prior;
            }
            $owner = PlotOwner::whereKey($data['plot_owner_id'])->lockForUpdate()->firstOrFail();
            $account = FinancialAccount::whereKey($data['financial_account_id'])->lockForUpdate()->firstOrFail();
            if (! $account->active || ! in_array($account->type, ['cash', 'bank', 'wallet'], true)) {
                throw ValidationException::withMessages(['financial_account_id' => 'Select an active cash, bank or wallet account.']);
            }
            if ($account->type !== 'cash' && ! filled($data['reference'] ?? null)) {
                throw ValidationException::withMessages(['reference' => 'A transaction reference is required for non-cash payouts.']);
            }
            $payable = $this->payable($owner->id, true);
            $pending = OwnerPayout::where('plot_owner_id', $owner->id)->where('state', 'prepared')->lockForUpdate()->sum('amount');
            $available = bcsub($payable, $this->numeric($pending), 2);
            if (bccomp($this->numeric($data['amount']), $available, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'The payout exceeds the available owner entitlement after pending requests.']);
            }
            $next = (int) OwnerPayout::lockForUpdate()->max('id') + 1;

            return OwnerPayout::create([...$data, 'number' => 'PAY-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT), 'state' => 'prepared', 'owner_snapshot' => $owner->only(['id', 'name']), 'account_snapshot' => $account->only(['id', 'code', 'name', 'type']), 'payable_snapshot' => $payable, 'prepared_by' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function decide(User $actor, OwnerPayout $payout, array $input): OwnerPayout
    {
        $data = Validator::make($input, ['decision' => ['required', Rule::in(['approve', 'reject'])], 'decision_note' => ['required', 'string', 'min:5', 'max:2000'], 'decision_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted']])->validate();

        return DB::transaction(function () use ($actor, $payout, $data) {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($fresh)->authorize('finance.owner-payouts.approve');
            abort_unless($fresh->hasAnyRole(['admin', 'super_admin']), 403);
            Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $record = OwnerPayout::whereKey($payout->id)->lockForUpdate()->firstOrFail();
            if ($record->decision_key === strtolower($data['decision_key'])) {
                return $record;
            }
            if ($record->state !== 'prepared') {
                throw new ConflictHttpException('This payout request has already been decided.');
            }
            if ($data['decision'] === 'approve' && bccomp($this->numeric($record->amount), $this->payable($record->plot_owner_id, true), 2) > 0) {
                throw ValidationException::withMessages(['decision' => 'The available entitlement changed and no longer covers this payout.']);
            }
            $record->update(['state' => $data['decision'] === 'approve' ? 'approved' : 'rejected', 'decided_by' => $fresh->id, 'decision_note' => trim($data['decision_note']), 'decided_at' => now(), 'decision_key' => strtolower($data['decision_key'])]);

            return $record;
        }, attempts: 3);
    }

    /** @return numeric-string */
    private function numeric(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value)) || ! is_numeric((string) $value)) {
            throw new \UnexpectedValueException('Expected exact numeric value.');
        }

        return bcadd((string) $value, '0', 2);
    }
}
