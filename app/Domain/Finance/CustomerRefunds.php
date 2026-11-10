<?php

namespace App\Domain\Finance;

use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Models\CustomerRefund;
use App\Models\FinancialAccount;
use App\Models\InstallmentAllocationCorrection;
use App\Models\OwnerEntitlementEvent;
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

class CustomerRefunds
{
    public function collected(int $bookingId, bool $lock = false): string
    {
        $a = DB::table('booking_allocations')->where('booking_id', $bookingId);
        $i = DB::table('installment_batches')->where('booking_id', $bookingId);
        $total = bcadd($this->numeric(($lock ? $a->lockForUpdate() : $a)->sum('amount')), $this->numeric(($lock ? $i->lockForUpdate() : $i)->sum('amount')), 2);
        $corrections = DB::table('installment_allocation_corrections')->where('booking_id', $bookingId)->where('state', 'approved');
        foreach (($lock ? $corrections->lockForUpdate() : $corrections)->get(['amount']) as $correction) {
            $total = bcsub($total, $this->numeric($correction->amount), 2);
        }

        return $total;
    }

    /** @return numeric-string */
    public function available(int $bookingId, bool $lock = false, bool $includePending = true): string
    {
        $q = CustomerRefund::where('booking_id', $bookingId)->whereIn('state', $includePending ? ['prepared', 'approved'] : ['approved']);
        $rows = ($lock ? $q->lockForUpdate() : $q)->get();
        $used = '0.00';
        foreach ($rows as $row) {
            $used = bcadd($used, bcadd($this->numeric($row->amount), $this->numeric($row->deduction_amount), 2), 2);
        }
        $corrections = InstallmentAllocationCorrection::where('booking_id', $bookingId)->where('state', 'prepared');
        foreach (($lock ? $corrections->lockForUpdate() : $corrections)->get(['amount']) as $correction) {
            $used = bcadd($used, $this->numeric($correction->amount), 2);
        }

        return bcsub($this->numeric($this->collected($bookingId, $lock)), $used, 2);
    }

    /** @param array<string,mixed> $input */
    public function prepare(User $actor, array $input): CustomerRefund
    {
        $d = Validator::make($input, ['booking_id' => ['required', 'integer'], 'financial_account_id' => ['required', 'integer'], 'amount' => ['required', new PositiveDecimal], 'deduction_amount' => ['nullable', 'regex:/^(?:0|[1-9][0-9]*)(?:\.[0-9]{1,2})?$/D'], 'deduction_reason' => ['nullable', 'string', 'max:2000'], 'business_date' => ['required', 'date_format:Y-m-d'], 'reference' => ['nullable', 'string', 'max:500'], 'verification_note' => ['required', 'string', 'min:10', 'max:2000'], 'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted']])->validate();
        $d['amount'] = $this->numeric($d['amount']);
        $d['deduction_amount'] = $this->numeric($d['deduction_amount'] ?? '0');
        if (bccomp($d['deduction_amount'], '0', 2) > 0 && ! filled($d['deduction_reason'] ?? null)) {
            throw ValidationException::withMessages(['deduction_reason' => 'Explain every approved deduction.']);
        }
        if ($d['business_date'] !== app(BusinessClock::class)->today()) {
            throw ValidationException::withMessages(['business_date' => 'Use the current Karachi business date.']);
        }
        $payload = json_encode(array_diff_key($d, ['command_key' => true, 'confirmed' => true]), JSON_THROW_ON_ERROR);
        $hashes = array_map(fn ($k) => hash_hmac('sha256', $payload, $k), [config('app.key'), ...config('app.previous_keys', [])]);

        return DB::transaction(function () use ($actor, $d, $hashes) {
            $u = User::lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($u)->authorize('finance.refunds.prepare');
            abort_unless($u->hasRole('accountant'), 403);
            Society::lockForUpdate()->findOrFail(1);
            $prior = CustomerRefund::where('prepared_by', $u->id)->where('command_key', strtolower($d['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This refund key was reused with different details.');
                }

return $prior;
            }
            $b = Booking::query()->whereKey((int) $d['booking_id'])->lockForUpdate()->firstOrFail();
            if (! BookingChangeRequest::where('booking_id', $b->id)->where('kind', 'cancellation')->where('state', 'approved')->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['booking_id' => 'An approved cancellation is required before preparing a refund.']);
            }
            $account = FinancialAccount::query()->whereKey((int) $d['financial_account_id'])->lockForUpdate()->firstOrFail();
            if (! $account->active || ! in_array($account->type, ['cash', 'bank', 'wallet'], true)) {
                throw ValidationException::withMessages(['financial_account_id' => 'Select an active cash, bank or wallet account.']);
            } if ($account->type !== 'cash' && ! filled($d['reference'] ?? null)) {
                throw ValidationException::withMessages(['reference' => 'A reference is required for bank or wallet refunds.']);
            }
            $available = $this->available($b->id, true);
            if (bccomp(bcadd($d['amount'], $d['deduction_amount'], 2), $available, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Refund plus deductions exceed the available collected credit.']);
            } $next = (int) CustomerRefund::lockForUpdate()->max('id') + 1;

            return CustomerRefund::create([...$d, 'number' => 'REF-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT), 'state' => 'prepared', 'booking_snapshot' => ['id' => $b->id, 'customer_name' => $b->snapshot['customer_name'], 'plot' => $b->snapshot['plot']['number']], 'account_snapshot' => $account->only(['id', 'code', 'name', 'type']), 'refundable_snapshot' => $available, 'prepared_by' => $u->id, 'command_key' => strtolower($d['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function decide(User $actor, CustomerRefund $refund, array $input): CustomerRefund
    {
        $d = Validator::make($input, ['decision' => ['required', Rule::in(['approve', 'reject'])], 'decision_note' => ['required', 'string', 'min:5', 'max:2000'], 'decision_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted']])->validate();

        return DB::transaction(function () use ($actor, $refund, $d) {
            $u = User::lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($u)->authorize('finance.refunds.approve');
            abort_unless($u->hasAnyRole(['admin', 'super_admin']), 403);
            Society::lockForUpdate()->findOrFail(1);
            $r = CustomerRefund::lockForUpdate()->findOrFail($refund->id);
            if ($r->decision_key === strtolower($d['decision_key'])) {
                return $r;
            }if ($r->state !== 'prepared') {
                throw new ConflictHttpException('This refund was already decided.');
            }
            if ($d['decision'] === 'approve') {
                $available = $this->available($r->booking_id, true, false);
                if (bccomp(bcadd($this->numeric($r->amount), $this->numeric($r->deduction_amount), 2), $available, 2) > 0) {
                    throw ValidationException::withMessages(['decision' => 'Available refundable credit changed.']);
                } $b = Booking::query()->whereKey($r->booking_id)->firstOrFail();
                $event = OwnerEntitlementEvent::where('booking_id', $b->id)->first();
                if ($event) {
                    $rate = $event->percentage;
                    $amount = $this->numeric($r->amount);
                    $raw = bcdiv(bcmul($amount, $rate, 6), '100', 6);
                    $value = bcdiv(bcadd($raw, '0.005000', 6), '1', 2);
                    OwnerEntitlementEvent::create(['plot_owner_id' => $event->plot_owner_id, 'booking_id' => $b->id, 'source_kind' => 'customer_refund', 'source_id' => $r->id, 'eligible_amount' => bcmul($amount, '-1', 2), 'percentage' => $rate, 'entitlement_amount' => bcmul($value, '-1', 2), 'business_date' => $r->business_date, 'policy_snapshot' => ['basis' => 'cash_refund', 'rounding' => 'half_up', 'deductions' => 'excluded']]);
                } app(AgentCommissions::class)->record($b, 'customer_refund', $r->id, bcmul($this->numeric($r->amount), '-1', 2), $r->business_date);
            }
            $r->update(['state' => $d['decision'] === 'approve' ? 'approved' : 'rejected', 'decided_by' => $u->id, 'decision_note' => trim($d['decision_note']), 'decided_at' => now(), 'decision_key' => strtolower($d['decision_key'])]);

            return $r;
        }, attempts: 3);
    }

    /** @return numeric-string */
    private function numeric(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value) && ! is_float($value)) || ! is_numeric((string) $value)) {
            throw new \UnexpectedValueException('Expected exact numeric value.');
        }

return bcadd((string) $value,'0',2);
    }
}
