<?php

namespace App\Domain\Finance;

use App\Models\Booking;
use App\Models\CommissionEvent;
use App\Models\CommissionPayment;
use App\Models\FinancialAccount;
use App\Models\LegacyCommissionAssignment;
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

class AgentCommissions
{
    public function record(Booking $b, string $kind, int $source, string $eligible, string $date): ?CommissionEvent
    {
        $legacy = null;
        $agent = $b->commission_agent_id;
        $rate = $b->commission_percentage;
        $snapshot = $b->commission_policy_snapshot;
        if (! is_int($agent) || $rate === null) {
            $legacy = LegacyCommissionAssignment::where('booking_id', $b->id)->where('state', 'active')->first();
            if (! $legacy || $date < $legacy->effective_from) {
                return null;
            }$agent = $legacy->agent_id;
            $rate = $legacy->percentage;
            $snapshot = $legacy->policy_snapshot;
        } $amount = $this->numeric($eligible);
        $pct = $this->numeric($rate, 4);
        $value = $this->calculate($amount, $pct);
        $e = CommissionEvent::firstOrCreate(['source_kind' => $kind, 'source_id' => $source], ['booking_id' => $b->id, 'agent_id' => $agent, 'eligible_amount' => $amount, 'percentage' => $pct, 'commission_amount' => $value, 'business_date' => $date, 'policy_snapshot' => $snapshot]);
        if ($e->booking_id !== $b->id || bccomp($e->eligible_amount, $amount, 2) !== 0) {
            throw new ConflictHttpException('This source already has a different commission event.');
        }

        return $e;
    }

    /** @param array<string,mixed> $input */
    public function assignLegacy(User $actor, int $bookingId, array $input): LegacyCommissionAssignment
    {
        $d = Validator::make($input, ['agent_id' => ['required', 'integer'], 'percentage' => ['required', 'regex:/^(?:100(?:\.0{1,4})?|(?:[0-9]{1,2})(?:\.[0-9]{1,4})?)$/D'], 'effective_from' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:10', 'max:2000'], 'confirmed' => ['required', 'accepted']])->validate();

        return DB::transaction(function () use ($actor, $bookingId, $d) {
            $u = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($u)->authorize('finance.commissions.configure');
            abort_unless($u->hasAnyRole(['admin', 'super_admin']), 403);
            $b = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();
            if ($b->commission_agent_id !== null) {
                throw ValidationException::withMessages(['booking_id' => 'This booking already has a commission snapshot.']);
            }$agent = User::whereKey((int) $d['agent_id'])->lockForUpdate()->firstOrFail();
            abort_unless($agent->hasRole('sales_agent'), 422);
            $old = LegacyCommissionAssignment::where('booking_id', $b->id)->where('state', 'active')->lockForUpdate()->first();
            if ($old) {
                throw ValidationException::withMessages(['booking_id' => 'This booking already has a legacy commission assignment. Use the controlled correction workflow.']);
            }$assignment = LegacyCommissionAssignment::create(['booking_id' => $b->id, 'agent_id' => $agent->id, 'percentage' => $this->numeric($d['percentage'], 4), 'effective_from' => $d['effective_from'], 'reason' => $d['reason'], 'state' => 'active', 'supersedes_id' => null, 'assigned_by' => $u->id, 'policy_snapshot' => ['basis' => 'cleared_collections', 'trigger' => 'collection_cleared', 'cancellation' => 'reverse_and_recover', 'source' => 'legacy_admin_assignment', 'reason' => $d['reason']]]);
            $sources = [];
            $down = DB::table('booking_allocations')->join('payment_receipts', 'payment_receipts.id', '=', 'booking_allocations.payment_receipt_id')->where('booking_id', $b->id)->where('payment_receipts.recognized_on', '>=', $d['effective_from'])->get(['booking_allocations.id', 'booking_allocations.amount', 'payment_receipts.recognized_on']);
            foreach ($down as $row) {
                $sources[] = ['kind' => 'booking_allocation', 'id' => (int) $row->id, 'amount' => $this->numeric($row->amount), 'date' => $row->recognized_on];
            }$inst = DB::table('installment_batches')->where('booking_id', $b->id)->where('business_date', '>=', $d['effective_from'])->get(['id', 'amount', 'business_date']);
            foreach ($inst as $row) {
                $sources[] = ['kind' => 'installment_batch', 'id' => (int) $row->id, 'amount' => $this->numeric($row->amount), 'date' => $row->business_date];
            }foreach ($sources as $row) {
                $this->record($b, $row['kind'], $row['id'], $row['amount'], $row['date']);
            }

            return $assignment;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function correctLegacy(User $actor, int $bookingId, array $input): LegacyCommissionAssignment
    {
        $d = Validator::make($input, ['agent_id' => ['required', 'integer'], 'percentage' => ['required', 'regex:/^(?:100(?:\\.0{1,4})?|(?:[0-9]{1,2})(?:\\.[0-9]{1,4})?)$/D'], 'effective_from' => ['required', 'date_format:Y-m-d'], 'reason' => ['required', 'string', 'min:10', 'max:2000'], 'confirmed' => ['required', 'accepted']])->validate();

        return DB::transaction(function () use ($actor, $bookingId, $d) {
            $u = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($u)->authorize('finance.commissions.correct');
            abort_unless($u->hasRole('super_admin'), 403);
            $b = Booking::whereKey($bookingId)->lockForUpdate()->firstOrFail();
            if ($b->commission_agent_id !== null) {
                throw ValidationException::withMessages(['booking_id' => 'Booking snapshots require a separate correction workflow.']);
            }
            $old = LegacyCommissionAssignment::where('booking_id', $b->id)->where('state', 'active')->lockForUpdate()->first();
            if (! $old) {
                throw ValidationException::withMessages(['booking_id' => 'No active legacy assignment exists for this booking.']);
            }
            $agent = User::whereKey((int) $d['agent_id'])->lockForUpdate()->firstOrFail();
            abort_unless($agent->hasRole('sales_agent'), 422);
            $agentIds = CommissionEvent::where('booking_id', $b->id)->lockForUpdate()->distinct()->pluck('agent_id');
            $balances = $agentIds->mapWithKeys(fn ($agentId) => [(int) $agentId => $this->numeric(CommissionEvent::where('booking_id', $b->id)->where('agent_id', $agentId)->lockForUpdate()->sum('commission_amount'))]);
            $nonZero = $balances->filter(fn ($balance) => bccomp($balance, '0.00', 2) !== 0);
            if ($nonZero->count() > 1) {
                throw new ConflictHttpException('This booking has commission balances for multiple agents and requires finance review.');
            }
            $oldBalance = $balances->get($old->agent_id, '0.00');
            $old->update(['state' => 'superseded']);
            $assignment = LegacyCommissionAssignment::create(['booking_id' => $b->id, 'agent_id' => $agent->id, 'percentage' => $this->numeric($d['percentage'], 4), 'effective_from' => $d['effective_from'], 'reason' => trim($d['reason']), 'state' => 'active', 'supersedes_id' => $old->id, 'assigned_by' => $u->id, 'policy_snapshot' => ['basis' => 'cleared_collections', 'trigger' => 'collection_cleared', 'cancellation' => 'reverse_and_recover', 'source' => 'legacy_admin_correction', 'reason' => trim($d['reason'])]]);
            $today = app(BusinessClock::class)->today();
            $policy = ['source' => 'legacy_admin_correction', 'assignment_id' => $assignment->id, 'supersedes_id' => $old->id, 'reason' => trim($d['reason'])];
            CommissionEvent::create(['booking_id' => $b->id, 'agent_id' => $old->agent_id, 'source_kind' => 'legacy_correction_debit', 'source_id' => $assignment->id, 'eligible_amount' => '0.00', 'percentage' => $old->percentage, 'commission_amount' => bcmul($oldBalance, '-1', 2), 'business_date' => $today, 'policy_snapshot' => $policy]);
            $desired = $this->legacyDesired($b->id, $d['effective_from'], $d['percentage']);
            CommissionEvent::create(['booking_id' => $b->id, 'agent_id' => $agent->id, 'source_kind' => 'legacy_correction_credit', 'source_id' => $assignment->id, 'eligible_amount' => '0.00', 'percentage' => $this->numeric($d['percentage'], 4), 'commission_amount' => $desired, 'business_date' => $today, 'policy_snapshot' => $policy]);

            return $assignment;
        }, attempts: 3);
    }

    /** @return numeric-string */
    private function legacyDesired(int $bookingId, string $from, mixed $percentage): string
    {
        $total = '0.00';
        $down = DB::table('booking_allocations')->join('payment_receipts', 'payment_receipts.id', '=', 'booking_allocations.payment_receipt_id')->where('booking_id', $bookingId)->where('payment_receipts.recognized_on', '>=', $from)->get(['booking_allocations.amount']);
        foreach ($down as $row) {
            $total = bcadd($total, $this->calculate($row->amount, $percentage), 2);
        }$inst = DB::table('installment_batches')->where('booking_id', $bookingId)->where('business_date', '>=', $from)->get(['amount']);
        foreach ($inst as $row) {
            $total = bcadd($total, $this->calculate($row->amount, $percentage), 2);
        }$refunds = DB::table('customer_refunds')->where('booking_id', $bookingId)->where('state', 'approved')->where('business_date', '>=', $from)->get(['amount']);
        foreach ($refunds as $row) {
            $total = bcadd($total, $this->calculate(bcmul($this->numeric($row->amount), '-1', 2), $percentage), 2);
        }

        return $total;
    }

    /** @return numeric-string */
    public function payable(int $agent, bool $lock = false): string
    {
        $e = CommissionEvent::where('agent_id', $agent);
        $p = CommissionPayment::where('agent_id', $agent)->where('state', 'approved');

        return bcsub($this->numeric(($lock ? $e->lockForUpdate() : $e)->sum('commission_amount')), $this->numeric(($lock ? $p->lockForUpdate() : $p)->sum('amount')), 2);
    }

    /** @param array<string,mixed> $input */
    public function prepare(User $actor, array $input): CommissionPayment
    {
        $d = Validator::make($input, ['agent_id' => ['required', 'integer'], 'financial_account_id' => ['required', 'integer'], 'amount' => ['required', new PositiveDecimal], 'business_date' => ['required', 'date_format:Y-m-d'], 'reference' => ['nullable', 'string', 'max:500'], 'verification_note' => ['required', 'string', 'min:10', 'max:2000'], 'command_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted']])->validate();
        $d['amount'] = $this->numeric($d['amount']);
        if ($d['business_date'] !== app(BusinessClock::class)->today()) {
            throw ValidationException::withMessages(['business_date' => 'Use the current Karachi business date.']);
        }$payload = json_encode(array_diff_key($d, ['command_key' => true, 'confirmed' => true]), JSON_THROW_ON_ERROR);
        $hashes = array_map(fn ($k) => hash_hmac('sha256', $payload, $k), [config('app.key'), ...config('app.previous_keys', [])]);

        return DB::transaction(function () use ($actor, $d, $hashes) {
            $u = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($u)->authorize('finance.commissions.prepare');
            abort_unless($u->hasRole('accountant'), 403);
            Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $prior = CommissionPayment::where('prepared_by', $u->id)->where('command_key', strtolower($d['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This payment key was reused.');
                }

                return $prior;
            }$agent = User::whereKey((int) $d['agent_id'])->lockForUpdate()->firstOrFail();
            abort_unless($agent->hasRole('sales_agent'), 422);
            $account = FinancialAccount::whereKey((int) $d['financial_account_id'])->lockForUpdate()->firstOrFail();
            if (! $account->active || ! in_array($account->type, ['cash', 'bank', 'wallet'], true)) {
                throw ValidationException::withMessages(['financial_account_id' => 'Select an active cash, bank or wallet account.']);
            }if ($account->type !== 'cash' && ! filled($d['reference'] ?? null)) {
                throw ValidationException::withMessages(['reference' => 'Reference is required for bank or wallet payments.']);
            }$payable = $this->payable($agent->id, true);
            $pending = $this->numeric(CommissionPayment::where('agent_id', $agent->id)->where('state', 'prepared')->lockForUpdate()->sum('amount'));
            $available = bcsub($payable, $pending, 2);
            if (bccomp($d['amount'], $available, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Payment exceeds available commission.']);
            }$next = (int) CommissionPayment::lockForUpdate()->max('id') + 1;

            return CommissionPayment::create([...$d, 'number' => 'COM-'.str_pad((string) $next, 6, '0', STR_PAD_LEFT), 'state' => 'prepared', 'agent_snapshot' => $agent->only(['id', 'name']), 'account_snapshot' => $account->only(['id', 'code', 'name', 'type']), 'payable_snapshot' => $payable, 'prepared_by' => $u->id, 'command_key' => strtolower($d['command_key']), 'payload_hash' => $hashes[0]]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function decide(User $actor, CommissionPayment $payment, array $input): CommissionPayment
    {
        $d = Validator::make($input, ['decision' => ['required', Rule::in(['approve', 'reject'])], 'decision_note' => ['required', 'string', 'min:5', 'max:2000'], 'decision_key' => ['required', 'uuid'], 'confirmed' => ['required', 'accepted']])->validate();

        return DB::transaction(function () use ($actor, $payment, $d) {
            $u = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($u)->authorize('finance.commissions.approve');
            abort_unless($u->hasAnyRole(['admin', 'super_admin']), 403);
            $p = CommissionPayment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->decision_key === strtolower($d['decision_key'])) {
                return $p;
            }if ($p->state !== 'prepared') {
                throw new ConflictHttpException('This payment was already decided.');
            }if ($d['decision'] === 'approve' && bccomp($this->numeric($p->amount), $this->payable($p->agent_id, true), 2) > 0) {
                throw ValidationException::withMessages(['decision' => 'Available commission changed.']);
            }$p->update(['state' => $d['decision'] === 'approve' ? 'approved' : 'rejected', 'decided_by' => $u->id, 'decision_note' => $d['decision_note'], 'decided_at' => now(), 'decision_key' => strtolower($d['decision_key'])]);

            return $p;
        }, attempts: 3);
    }

    /** @return numeric-string */
    private function numeric(mixed $v, int $scale = 2): string
    {
        if ((! is_string($v) && ! is_int($v) && ! is_float($v)) || ! is_numeric((string) $v)) {
            throw new \UnexpectedValueException('Expected numeric value.');
        }

        return bcadd((string) $v, '0', $scale);
    }

    /** @return numeric-string */
    private function calculate(mixed $amount, mixed $percentage): string
    {
        $money = $this->numeric($amount);
        $rate = $this->numeric($percentage, 4);
        $negative = str_starts_with($money, '-');
        $raw = bcdiv(bcmul(ltrim($money, '-'), $rate, 6), '100', 6);
        $value = bcdiv(bcadd($raw, '0.005000', 6), '1', 2);

        return $negative ? bcmul($value, '-1', 2) : $value;
    }
}
