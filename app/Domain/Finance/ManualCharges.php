<?php

namespace App\Domain\Finance;

use App\Models\BookingCharge;
use App\Models\BookingChargeWaiver;
use App\Models\BusinessPolicy;
use App\Models\Society;
use App\Models\User;
use App\Rules\PositiveDecimal;
use App\Support\BusinessClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ManualCharges
{
    /** @return Builder<BookingCharge> */
    public function query(User $actor): Builder
    {
        Gate::forUser($actor)->authorize('finance.charges.view');

        return BookingCharge::whereIn('booking_id', app(InstallmentLedger::class)->query($actor)->select('id'));
    }

    public function canManage(User $actor): bool
    {
        return $actor->can('finance.charges.manage') && $actor->hasAnyRole(['super_admin', 'admin']);
    }

    /** @param array<string,mixed> $input */
    public function post(User $actor, array $input): BookingCharge
    {
        $today = app(BusinessClock::class)->today();
        $data = Validator::make($input, [
            'booking_id' => ['required', 'integer', 'min:1'], 'amount' => ['required', new PositiveDecimal],
            'business_date' => ['required', Rule::in([$today])], 'reason' => ['required', 'string', 'max:160'],
            'note' => ['required', 'string', 'max:1000'], 'evidence_reference' => ['nullable', 'string', 'max:160'],
            'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'], 'posted_by' => ['prohibited'],
        ])->validate();
        $values = ['booking_id' => (int) $data['booking_id'], 'amount' => bcadd($data['amount'], '0', 2), 'business_date' => $today,
            'reason' => trim($data['reason']), 'note' => trim($data['note']), 'evidence_reference' => trim($data['evidence_reference'] ?? '') ?: null];
        $hashes = $this->hashes($values);

        return DB::transaction(function () use ($actor, $data, $values, $hashes): BookingCharge {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canManage($fresh), 403);
            Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $booking = app(InstallmentLedger::class)->query($fresh)->whereKey($values['booking_id'])->lockForUpdate()->firstOrFail();
            $existing = BookingCharge::where('posted_by', $fresh->id)->where('command_key', strtolower($data['command_key']))->first();
            if ($existing) {
                if (! in_array($existing->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This charge key was already used with different details.');
                }

                return $existing;
            }
            $policy = BusinessPolicy::whereKey('charges')->lockForUpdate()->first();
            if (($policy->values['late_fee_mode'] ?? null) !== 'manual') {
                throw ValidationException::withMessages(['amount' => 'Main admin must select manual charge entry in Business settings first.']);
            }
            $series = DB::table('booking_charge_series')->where('id', 1)->lockForUpdate()->firstOrFail();
            $number = 'CHG-'.str_pad((string) $series->next_number, 6, '0', STR_PAD_LEFT);
            DB::table('booking_charge_series')->where('id', 1)->update(['next_number' => $series->next_number + 1]);

            return BookingCharge::create([...$values, 'booking_id' => $booking->id, 'number' => $number, 'posted_by' => $fresh->id,
                'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function waive(User $actor, int $id, array $input): BookingChargeWaiver
    {
        $today = app(BusinessClock::class)->today();
        $data = Validator::make($input, [
            'amount' => ['required', new PositiveDecimal], 'business_date' => ['required', Rule::in([$today])], 'reason' => ['required', 'string', 'max:500'],
            'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted'],
        ])->validate();
        $values = ['charge_id' => $id, 'amount' => bcadd($data['amount'], '0', 2), 'business_date' => $today, 'reason' => trim($data['reason'])];
        if ($values['reason'] === '') {
            throw ValidationException::withMessages(['reason' => 'Enter a reason for this waiver.']);
        }
        $hashes = $this->hashes($values);

        return DB::transaction(function () use ($actor, $id, $data, $values, $hashes, $today): BookingChargeWaiver {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            abort_unless($this->canManage($fresh), 403);
            Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $charge = $this->query($fresh)->whereKey($id)->lockForUpdate()->firstOrFail();
            $existing = BookingChargeWaiver::where('waived_by', $fresh->id)->where('command_key', strtolower($data['command_key']))->first();
            if ($existing) {
                if (! in_array($existing->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This waiver key was already used with different details.');
                }

                return $existing;
            }
            $balance = app(ChargePayments::class)->balance($charge, true);
            if (bccomp($balance, '0', 2) <= 0) {
                throw new ConflictHttpException('This charge has no remaining amount to waive.');
            }
            if (bccomp($values['amount'], $balance, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'The waiver exceeds the charge amount that remains unpaid and unwaived.']);
            }

            return BookingChargeWaiver::create(['booking_charge_id' => $charge->id, 'amount' => $values['amount'], 'business_date' => $today, 'reason' => $values['reason'],
                'waived_by' => $fresh->id, 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $values
     * @return list<string>
     */
    private function hashes(array $values): array
    {
        $payload = json_encode($values, JSON_THROW_ON_ERROR);

        return array_values(array_map(fn (string $key): string => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]));
    }
}
