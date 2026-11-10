<?php

namespace App\Domain\Finance;

use App\Domain\CRM\Customers;
use App\Domain\Sales\BookingTransfers;
use App\Models\AccountMovement;
use App\Models\Booking;
use App\Models\BookingChargePaymentBatch;
use App\Models\BusinessPolicy;
use App\Models\CommissionEvent;
use App\Models\InstallmentAllocationCorrection;
use App\Models\OwnerEntitlementEvent;
use App\Models\PaymentReceipt;
use App\Models\Society;
use App\Models\User;
use App\Rules\PositiveDecimal;
use App\Support\BusinessClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InstallmentLedger
{
    /** @return Builder<Booking> */
    public function query(User $actor): Builder
    {
        return Booking::whereIn('customer_id', app(ReceiptDrafts::class)->customers($actor)->select('id'));
    }

    public function canAllocate(User $actor): bool
    {
        return $actor->can('finance.allocations.post') && $actor->hasAnyRole(['admin', 'super_admin', 'accountant']);
    }

    public function canRequestCorrection(User $actor): bool
    {
        return $actor->can('finance.allocations.post') && $actor->hasRole('accountant');
    }

    public function canApproveCorrections(User $actor): bool
    {
        return $actor->can('finance.allocations.correct') && $actor->hasAnyRole(['admin', 'super_admin']);
    }

    /** @return array<string,mixed> */
    public function context(User $actor, int $id): array
    {
        $booking = $this->query($actor)->findOrFail($id);
        $state = $this->state($booking);
        $credits = [];
        $activeCustomerIds = array_column(app(BookingTransfers::class)->activeParties($booking), 'customer_id');
        foreach (PaymentReceipt::whereIn('customer_id', $activeCustomerIds)->where('state', 'recognized')->orderBy('id')->get() as $receipt) {
            $available = bcsub($receipt->amount, app(ReceiptCredit::class)->used($receipt->id), 2);
            if (bccomp($available, '0', 2) > 0) {
                $credits[] = ['id' => $receipt->id, 'number' => $receipt->number, 'available' => $available];
            }
        }
        $charges = app(ChargePayments::class)->balances($id);
        $outstandingCharges = '0.00';
        foreach ($charges as $charge) {
            $outstandingCharges = bcadd($outstandingCharges, $charge['remaining'], 2);
        }
        $policy = BusinessPolicy::find('collections');

        $correctionItems = [];
        if ($this->canRequestCorrection($actor)) {
            $allocations = DB::table('installment_allocations')
                ->join('installment_batches', 'installment_batches.id', '=', 'installment_allocations.installment_batch_id')
                ->join('payment_receipts', 'payment_receipts.id', '=', 'installment_allocations.payment_receipt_id')
                ->where('installment_allocations.booking_id', $id)
                ->orderBy('installment_allocations.id')
                ->get(['installment_allocations.id', 'installment_allocations.sequence', 'installment_allocations.amount', 'installment_batches.business_date', 'payment_receipts.number']);
            foreach ($allocations as $allocation) {
                $reserved = InstallmentAllocationCorrection::where('installment_allocation_id', $allocation->id)->whereIn('state', ['prepared', 'approved'])->sum('amount');
                $available = bcsub($allocation->amount, $this->numeric($reserved), 2);
                if (bccomp($available, '0', 2) > 0) {
                    $correctionItems[] = ['id' => (int) $allocation->id, 'sequence' => (int) $allocation->sequence, 'amount' => $allocation->amount,
                        'available' => $available, 'business_date' => $allocation->business_date, 'number' => $allocation->number];
                }
            }
        }
        $corrections = ($this->canRequestCorrection($actor) || $this->canApproveCorrections($actor))
            ? DB::table('installment_allocation_corrections')
                ->join('installment_allocations', 'installment_allocations.id', '=', 'installment_allocation_corrections.installment_allocation_id')
                ->join('payment_receipts', 'payment_receipts.id', '=', 'installment_allocations.payment_receipt_id')
                ->join('users as preparers', 'preparers.id', '=', 'installment_allocation_corrections.prepared_by')
                ->leftJoin('users as deciders', 'deciders.id', '=', 'installment_allocation_corrections.decided_by')
                ->where('installment_allocation_corrections.booking_id', $id)->orderByDesc('installment_allocation_corrections.id')
                ->get(['installment_allocation_corrections.id', 'installment_allocation_corrections.amount', 'installment_allocation_corrections.reason',
                    'installment_allocation_corrections.state', 'installment_allocation_corrections.decision_note', 'installment_allocation_corrections.business_date',
                    'installment_allocation_corrections.ledger_version', 'installment_allocations.sequence', 'payment_receipts.number',
                    'preparers.name as preparer_name', 'deciders.name as decider_name'])
                ->map(fn ($row) => [...(array) $row, 'decision_key' => (string) Str::uuid()])->all()
            : [];
        $chargeHistory = DB::table('booking_charge_payment_batches')
            ->join('payment_receipts', 'payment_receipts.id', '=', 'booking_charge_payment_batches.payment_receipt_id')
            ->leftJoin('booking_charge_payment_reversals', 'booking_charge_payment_reversals.booking_charge_payment_batch_id', '=', 'booking_charge_payment_batches.id')
            ->where('booking_charge_payment_batches.booking_id', $id)
            ->latest('booking_charge_payment_batches.version')->limit(20)
            ->get(['booking_charge_payment_batches.version', 'booking_charge_payment_batches.amount', 'booking_charge_payment_batches.business_date', 'payment_receipts.number', 'booking_charge_payment_reversals.id as reversal_id'])
            ->map(fn ($row) => [...(array) $row, 'reversed' => $row->reversal_id !== null]);

        return ['booking' => $booking->only(['id', 'net', 'down_payment', 'confirmed_on']) + ['customer_name' => implode(', ', array_column(app(BookingTransfers::class)->activeParties($booking), 'name')), 'plot' => $booking->snapshot['plot']['number']],
            ...$state, 'credits' => $credits, 'canAllocate' => $this->canAllocate($actor), 'canRequestCorrection' => $this->canRequestCorrection($actor),
            'canApproveCorrections' => $this->canApproveCorrections($actor), 'correctionItems' => $correctionItems, 'corrections' => $corrections,
            'correctionCommandKey' => (string) Str::uuid(), 'policyVersion' => $policy->version ?? 0, 'rule' => $policy->values['allocation'] ?? null,
            'charges' => $charges, 'outstandingCharges' => $outstandingCharges, 'chargeHistory' => $chargeHistory,
            'history' => DB::table('installment_batches')->join('payment_receipts', 'payment_receipts.id', '=', 'installment_batches.payment_receipt_id')->where('booking_id', $id)->latest('installment_batches.version')->limit(20)->get(['installment_batches.version', 'installment_batches.amount', 'installment_batches.business_date', 'installment_batches.actor_id', 'payment_receipts.number'])];
    }

    /** @return array{schedule:list<array{sequence:int,due_date:string,amount:numeric-string,paid:numeric-string,remaining:numeric-string,status:string}>,outstanding:numeric-string,overdue:numeric-string,paidPrincipal:numeric-string,version:int,businessDate:string} */
    public function state(Booking $booking, bool $lock = false): array
    {
        $query = DB::table('installment_allocations')->where('booking_id', $booking->id);
        $rows = ($lock ? $query->lockForUpdate() : $query)->get();
        $paid = [];
        foreach ($rows as $row) {
            $paid[$row->sequence] = bcadd($paid[$row->sequence] ?? '0.00', $row->amount, 2);
        }
        $correctionsQuery = DB::table('installment_allocation_corrections')
            ->join('installment_allocations', 'installment_allocations.id', '=', 'installment_allocation_corrections.installment_allocation_id')
            ->where('installment_allocation_corrections.booking_id', $booking->id)
            ->where('installment_allocation_corrections.state', 'approved');
        $corrections = ($lock ? $correctionsQuery->lockForUpdate() : $correctionsQuery)->get([
            'installment_allocation_corrections.installment_allocation_id', 'installment_allocation_corrections.amount',
            'installment_allocation_corrections.ledger_version', 'installment_allocations.sequence',
        ]);
        foreach ($corrections as $correction) {
            $paid[$correction->sequence] = bcsub($paid[$correction->sequence] ?? '0.00', $correction->amount, 2);
        }
        $schedule = [];
        $outstanding = '0.00';
        $overdue = '0.00';
        $today = app(BusinessClock::class)->today();
        foreach ($booking->snapshot['terms']['schedule'] as $entry) {
            $applied = bcadd($paid[$entry['sequence']] ?? '0.00', '0', 2);
            $original = bcadd($entry['amount'], '0', 2);
            $remaining = bcsub($original, $applied, 2);
            $outstanding = bcadd($outstanding, $remaining, 2);
            $late = $entry['due_date'] < $today && bccomp($remaining, '0', 2) > 0;
            if ($late) {
                $overdue = bcadd($overdue, $remaining, 2);
            }
            $schedule[] = ['sequence' => (int) $entry['sequence'], 'due_date' => (string) $entry['due_date'], 'amount' => $original, 'paid' => $applied, 'remaining' => $remaining, 'status' => bccomp($remaining, '0', 2) === 0 ? 'Paid' : ($late ? 'Overdue' : ($entry['due_date'] === $today ? 'Due today' : 'Upcoming'))];
        }
        $batches = DB::table('installment_batches')->where('booking_id', $booking->id)->orderByDesc('version');
        $latest = ($lock ? $batches->lockForUpdate() : $batches)->first();
        $version = (int) ($latest->version ?? 0);
        $chargeBatches = BookingChargePaymentBatch::query()->where('booking_id', $booking->id)->orderByDesc('version');
        $chargeLatest = ($lock ? $chargeBatches->lockForUpdate() : $chargeBatches)->first();
        $version = max($version, (int) ($chargeLatest->version ?? 0));
        foreach ($corrections as $correction) {
            $version = max($version, (int) $correction->ledger_version);
        }

        return ['schedule' => $schedule, 'outstanding' => $outstanding, 'overdue' => $overdue, 'paidPrincipal' => bcsub($booking->net, $outstanding, 2), 'version' => $version, 'businessDate' => $today];
    }

    /** @param array<string,mixed> $input */
    public function allocate(User $actor, int $id, array $input): int
    {
        $data = Validator::make($input, [
            'command_key' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:0'], 'policy_version' => ['required', 'integer', 'min:1'],
            'receipt_id' => ['required', 'integer', 'min:1'], 'amount' => ['required', new PositiveDecimal], 'mode' => ['required', Rule::in(['oldest_first', 'manual'])],
            'rows' => ['present', 'array', 'max:360'], 'rows.*' => ['array:sequence,amount'], 'rows.*.sequence' => ['required', 'integer', 'min:1', 'max:360', 'distinct'], 'rows.*.amount' => ['required', new PositiveDecimal],
            'charge_rows' => ['present', 'array', 'max:1000'], 'charge_rows.*' => ['array:charge_id,amount'],
            'charge_rows.*.charge_id' => ['required', 'integer', 'min:1', 'distinct'], 'charge_rows.*.amount' => ['required', new PositiveDecimal],
            'confirmed' => ['required', 'accepted'], 'actor_id' => ['prohibited'], 'business_date' => ['prohibited'],
        ])->validate();
        $data['amount'] = bcadd($data['amount'], '0', 2);
        $data['rows'] = $data['mode'] === 'manual' ? array_map(fn ($row) => ['sequence' => (int) $row['sequence'], 'amount' => bcadd($row['amount'], '0', 2)], $data['rows']) : [];
        $data['charge_rows'] = array_map(fn ($row) => ['charge_id' => (int) $row['charge_id'], 'amount' => bcadd($row['amount'], '0', 2)], $data['charge_rows']);
        usort($data['rows'], fn ($a, $b) => $a['sequence'] <=> $b['sequence']);
        usort($data['charge_rows'], fn ($a, $b) => $a['charge_id'] <=> $b['charge_id']);
        $payload = json_encode([$id, (int) $data['receipt_id'], (int) $data['version'], (int) $data['policy_version'], $data['mode'], $data['amount'], $data['rows'], $data['charge_rows']], JSON_THROW_ON_ERROR);
        $hashes = array_map(fn ($key) => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]);

        return DB::transaction(function () use ($actor, $id, $data, $hashes) {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($fresh)->authorize('finance.allocations.post');
            abort_unless($this->canAllocate($fresh), 403);
            Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $booking = $this->query($fresh)->lockForUpdate()->findOrFail($id);
            $prior = DB::table('installment_batches')->where('actor_id', $fresh->id)->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This allocation key was already used with different details.');
                }

                return $prior->id;
            }
            $chargePrior = BookingChargePaymentBatch::where('actor_id', $fresh->id)
                ->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($chargePrior) {
                if (! in_array($chargePrior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This allocation key was already used with different details.');
                }

                return $chargePrior->id;
            }
            $policy = BusinessPolicy::whereKey('collections')->lockForUpdate()->firstOrFail();
            if ($policy->version !== (int) $data['policy_version']) {
                throw new ConflictHttpException('Collection settings changed. Reload before allocating.');
            }
            $rule = $policy->values['allocation'] ?? null;
            if (! in_array($rule, ['oldest_first', 'manual', 'hybrid'], true) || ($rule !== 'hybrid' && $rule !== $data['mode'])) {
                throw ValidationException::withMessages(['mode' => 'Use the allocation method saved by main admin.']);
            }
            $state = $this->state($booking, true);
            $posting = BusinessPolicy::whereKey('receipt_posting')->lockForUpdate()->first();
            if (empty($posting->values['open_from']) || $state['businessDate'] < $posting->values['open_from']) {
                throw ValidationException::withMessages(['amount' => 'Main admin must open the current posting date before allocating credit.']);
            }
            if ($state['version'] !== (int) $data['version']) {
                throw new ConflictHttpException('This ledger changed. Reload current remaining amounts.');
            }
            $chargeOutstanding = app(ChargePayments::class)->outstanding($id, true);
            $maximum = bcadd($state['outstanding'], $chargeOutstanding, 2);
            $principalAmount = bccomp($data['amount'], $state['outstanding'], 2) <= 0 ? $data['amount'] : $state['outstanding'];
            $chargeAmount = bcsub($data['amount'], $principalAmount, 2);
            if (bccomp($chargeAmount, '0', 2) === 0 && $data['charge_rows'] !== []) {
                throw ValidationException::withMessages(['charge_rows' => 'Do not select charges when the payment does not exceed remaining principal.']);
            }
            $receipt = PaymentReceipt::whereKey($data['receipt_id'])->lockForUpdate()->firstOrFail();
            $activeCustomerIds = array_column(app(BookingTransfers::class)->activeParties($booking, true), 'customer_id');
            abort_unless(in_array($receipt->customer_id, $activeCustomerIds, true), 404);
            $credit = AccountMovement::where('payment_receipt_id', $receipt->id)->where('kind', 'receipt_credit')->lockForUpdate()->first();
            $available = bcsub($receipt->amount, app(ReceiptCredit::class)->used($receipt->id, true), 2);
            if ($receipt->state !== 'recognized' || ! $credit || $receipt->recognized_on > $state['businessDate'] || bccomp($credit->amount, $receipt->amount, 2) !== 0 || bccomp($data['amount'], $available, 2) > 0 || bccomp($data['amount'], $maximum, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Use recognized customer credit within the remaining principal and charge balances.']);
            }
            $entries = array_column($state['schedule'], null, 'sequence');
            $applied = [];
            if ($data['mode'] === 'oldest_first') {
                $remaining = $principalAmount;
                $ordered = $state['schedule'];
                usort($ordered, fn ($a, $b) => strcmp($a['due_date'], $b['due_date']) ?: $a['sequence'] <=> $b['sequence']);
                foreach ($ordered as $entry) {
                    if (bccomp($remaining, '0', 2) <= 0 || bccomp($entry['remaining'], '0', 2) <= 0) {
                        continue;
                    }
                    $amount = bccomp($remaining, $entry['remaining'], 2) <= 0 ? $remaining : $entry['remaining'];
                    $applied[] = ['sequence' => $entry['sequence'], 'amount' => $amount];
                    $remaining = bcsub($remaining, $amount, 2);
                }
            } else {
                $total = '0.00';
                foreach ($data['rows'] as $row) {
                    $entry = $entries[$row['sequence']] ?? null;
                    if (! $entry || bccomp($row['amount'], $entry['remaining'], 2) > 0) {
                        throw ValidationException::withMessages(['rows' => 'Every selected installment must exist and have enough unpaid principal.']);
                    }
                    $total = bcadd($total, $row['amount'], 2);
                    $applied[] = $row;
                }
                if (bccomp($total, $principalAmount, 2) !== 0) {
                    throw ValidationException::withMessages(['rows' => 'Manual installment amounts must equal the principal portion of this allocation.']);
                }
            }
            $batch = null;
            if (bccomp($principalAmount, '0', 2) > 0) {
                $batch = DB::table('installment_batches')->insertGetId(['booking_id' => $id, 'payment_receipt_id' => $receipt->id, 'actor_id' => $fresh->id, 'version' => $state['version'] + 1, 'amount' => $principalAmount, 'business_date' => $state['businessDate'], 'policy_snapshot' => json_encode(['version' => $policy->version, 'mode' => $data['mode'], 'values' => $policy->values, 'posting_version' => $posting->version, 'open_from' => $posting->values['open_from'], 'charge_amount' => $chargeAmount], JSON_THROW_ON_ERROR), 'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0], 'created_at' => now()]);
                foreach ($applied as $row) {
                    DB::table('installment_allocations')->insert(['installment_batch_id' => $batch, 'booking_id' => $id, 'payment_receipt_id' => $receipt->id, ...$row]);
                }
            }
            if (bccomp($chargeAmount, '0', 2) > 0) {
                $chargeBatch = app(ChargePayments::class)->allocate($id, $receipt->id, $fresh->id, $state['version'] + 1, $chargeAmount, $state['businessDate'], strtolower($data['command_key']), $hashes, $data['charge_rows']);
                $batch ??= $chargeBatch->id;
            }
            if ($batch === null) {
                throw ValidationException::withMessages(['amount' => 'Enter a positive amount to allocate.']);
            }
            if (bccomp($principalAmount, '0', 2) > 0) {
                if (data_get($booking->snapshot, 'plot.owner.id') !== null) {
                    app(OwnerEntitlements::class)->record($booking, 'installment_batch', $batch, $principalAmount, $state['businessDate']);
                }
                app(AgentCommissions::class)->record($booking, 'installment_batch', $batch, $principalAmount, $state['businessDate']);
            }
            app(Customers::class)->activity($fresh, $receipt->customer_id, 'installments.allocated', [
                'booking_id' => $id, 'batch_id' => $batch, 'amount' => $data['amount'],
                'principal_amount' => $principalAmount, 'charge_amount' => $chargeAmount, 'receipt_id' => $receipt->id,
            ]);

            return $batch;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function requestCorrection(User $actor, int $bookingId, array $input): InstallmentAllocationCorrection
    {
        $data = Validator::make($input, [
            'allocation_id' => ['required', 'integer', 'min:1'],
            'version' => ['required', 'integer', 'min:0'],
            'amount' => ['required', new PositiveDecimal],
            'reason' => ['required', 'string', 'min:10', 'max:2000'],
            'command_key' => ['required', 'uuid'],
            'confirmed' => ['required', 'accepted'],
            'business_date' => ['prohibited'],
        ])->validate();
        $data['amount'] = bcadd($data['amount'], '0', 2);
        $data['reason'] = trim($data['reason']);
        $payload = json_encode([$bookingId, (int) $data['allocation_id'], (int) $data['version'], $data['amount'], $data['reason']], JSON_THROW_ON_ERROR);
        $hashes = array_map(fn ($key) => hash_hmac('sha256', $payload, $key), [config('app.key'), ...config('app.previous_keys', [])]);

        return DB::transaction(function () use ($actor, $bookingId, $data, $hashes) {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($fresh)->authorize('finance.allocations.post');
            abort_unless($this->canRequestCorrection($fresh), 403);
            Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $booking = $this->query($fresh)->lockForUpdate()->findOrFail($bookingId);
            $prior = InstallmentAllocationCorrection::where('prepared_by', $fresh->id)
                ->where('command_key', strtolower($data['command_key']))->lockForUpdate()->first();
            if ($prior) {
                if (! in_array($prior->payload_hash, $hashes, true)) {
                    throw new ConflictHttpException('This correction key was already used with different details.');
                }

                return $prior;
            }
            $state = $this->state($booking, true);
            if ($state['version'] !== (int) $data['version']) {
                throw new ConflictHttpException('This ledger changed. Reload before requesting a correction.');
            }
            $allocation = DB::table('installment_allocations')->where('booking_id', $bookingId)
                ->where('id', $data['allocation_id'])->lockForUpdate()->first();
            if (! $allocation) {
                abort(404);
            }
            $receipt = PaymentReceipt::whereKey($allocation->payment_receipt_id)->lockForUpdate()->firstOrFail();
            if ($receipt->state !== 'recognized') {
                throw ValidationException::withMessages(['allocation_id' => 'Only allocations backed by a recognized receipt can be corrected.']);
            }
            $reserved = InstallmentAllocationCorrection::where('installment_allocation_id', $allocation->id)
                ->whereIn('state', ['prepared', 'approved'])->lockForUpdate()->get();
            $used = '0.00';
            foreach ($reserved as $correction) {
                $used = bcadd($used, $this->numeric($correction->amount), 2);
            }
            $correctable = bcsub($this->numeric($allocation->amount), $used, 2);
            if (bccomp($data['amount'], $correctable, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'The correction exceeds the allocation amount that remains uncorrected.']);
            }
            $available = app(CustomerRefunds::class)->available($bookingId, true);
            if (bccomp($data['amount'], $available, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'The correction would invalidate existing or pending customer refunds.']);
            }
            $businessDate = app(BusinessClock::class)->today();
            $posting = BusinessPolicy::whereKey('receipt_posting')->lockForUpdate()->first();
            if (empty($posting?->values['open_from']) || $businessDate < $posting->values['open_from']) {
                throw ValidationException::withMessages(['amount' => 'Main admin must open the current posting date before correcting allocations.']);
            }
            $correction = InstallmentAllocationCorrection::create([
                'booking_id' => $bookingId, 'installment_allocation_id' => $allocation->id, 'prepared_by' => $fresh->id,
                'amount' => $data['amount'], 'reason' => $data['reason'], 'state' => 'prepared',
                'command_key' => strtolower($data['command_key']), 'payload_hash' => $hashes[0],
            ]);
            app(Customers::class)->activity($fresh, $receipt->customer_id, 'installments.correction_prepared', [
                'booking_id' => $bookingId, 'correction_id' => $correction->id, 'allocation_id' => $allocation->id, 'amount' => $data['amount'],
            ]);

            return $correction;
        }, attempts: 3);
    }

    /** @param array<string,mixed> $input */
    public function decideCorrection(User $actor, int $correctionId, array $input): InstallmentAllocationCorrection
    {
        $data = Validator::make($input, [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'decision_note' => ['required', 'string', 'min:5', 'max:2000'],
            'decision_key' => ['required', 'uuid'],
            'confirmed' => ['required', 'accepted'],
        ])->validate();

        return DB::transaction(function () use ($actor, $correctionId, $data) {
            $fresh = User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($fresh)->authorize('finance.allocations.correct');
            abort_unless($this->canApproveCorrections($fresh), 403);
            Society::whereKey(1)->lockForUpdate()->firstOrFail();
            $correction = InstallmentAllocationCorrection::whereKey($correctionId)->lockForUpdate()->firstOrFail();
            if ($correction->decision_key === strtolower($data['decision_key'])) {
                if (
                    $correction->decided_by !== $fresh->id
                    || $correction->state !== ($data['decision'] === 'approve' ? 'approved' : 'rejected')
                    || $correction->decision_note !== trim($data['decision_note'])
                ) {
                    throw new ConflictHttpException('This correction decision key was reused with different details.');
                }

                return $correction;
            }
            if ($correction->state !== 'prepared') {
                throw new ConflictHttpException('This allocation correction was already decided.');
            }
            if ($correction->prepared_by === $fresh->id) {
                abort(403, 'The requester cannot approve their own correction.');
            }
            $booking = Booking::whereKey($correction->booking_id)->lockForUpdate()->firstOrFail();
            $allocation = DB::table('installment_allocations')->where('booking_id', $booking->id)
                ->where('id', $correction->installment_allocation_id)->lockForUpdate()->firstOrFail();
            $receipt = PaymentReceipt::whereKey($allocation->payment_receipt_id)->lockForUpdate()->firstOrFail();
            $decision = $data['decision'];
            $date = app(BusinessClock::class)->today();
            $ledgerVersion = null;
            if ($decision === 'approve') {
                $state = $this->state($booking, true);
                $available = app(CustomerRefunds::class)->available($booking->id, true);
                if (bccomp($available, '0', 2) < 0 || $receipt->state !== 'recognized') {
                    throw ValidationException::withMessages(['decision' => 'The credit or receipt state changed; review this correction again.']);
                }
                $datePolicy = BusinessPolicy::whereKey('receipt_posting')->lockForUpdate()->first();
                if (empty($datePolicy?->values['open_from']) || $date < $datePolicy->values['open_from']) {
                    throw ValidationException::withMessages(['decision' => 'The current posting date is not open for corrections.']);
                }
                $ledgerVersion = $state['version'] + 1;
                $this->recordCorrectionEntitlements($booking, (int) $allocation->installment_batch_id, $correction, $date);
            }
            $correction->update([
                'state' => $decision === 'approve' ? 'approved' : 'rejected',
                'decided_by' => $fresh->id, 'decision_note' => trim($data['decision_note']),
                'business_date' => $decision === 'approve' ? $date : null,
                'ledger_version' => $ledgerVersion, 'decided_at' => now(), 'decision_key' => strtolower($data['decision_key']),
            ]);
            app(Customers::class)->activity($fresh, $receipt->customer_id, 'installments.correction_'.$decision, [
                'booking_id' => $booking->id, 'correction_id' => $correction->id, 'allocation_id' => $allocation->id, 'amount' => $correction->amount,
            ]);

            return $correction;
        }, attempts: 3);
    }

    private function recordCorrectionEntitlements(Booking $booking, int $batchId, InstallmentAllocationCorrection $correction, string $date): void
    {
        $amount = $this->numeric($correction->amount);
        $ownerEvent = OwnerEntitlementEvent::where('source_kind', 'installment_batch')->where('source_id', $batchId)->lockForUpdate()->first();
        if ($ownerEvent) {
            $raw = bcdiv(bcmul($amount, $ownerEvent->percentage, 6), '100', 6);
            $offset = bcdiv(bcadd($raw, '0.005000', 6), '1', 2);
            OwnerEntitlementEvent::create([
                'plot_owner_id' => $ownerEvent->plot_owner_id, 'booking_id' => $booking->id,
                'source_kind' => 'allocation_correction', 'source_id' => $correction->id,
                'eligible_amount' => bcmul($amount, '-1', 2), 'percentage' => $ownerEvent->percentage,
                'entitlement_amount' => bcmul($offset, '-1', 2), 'business_date' => $date,
                'policy_snapshot' => ['basis' => 'allocation_reversal', 'rounding' => 'half_up', 'source_batch_id' => $batchId],
            ]);
        }
        $commissionEvent = CommissionEvent::where('source_kind', 'installment_batch')->where('source_id', $batchId)->lockForUpdate()->first();
        if ($commissionEvent) {
            $raw = bcdiv(bcmul($amount, $commissionEvent->percentage, 6), '100', 6);
            $offset = bcdiv(bcadd($raw, '0.005000', 6), '1', 2);
            CommissionEvent::create([
                'booking_id' => $booking->id, 'agent_id' => $commissionEvent->agent_id,
                'source_kind' => 'allocation_correction', 'source_id' => $correction->id,
                'eligible_amount' => bcmul($amount, '-1', 2), 'percentage' => $commissionEvent->percentage,
                'commission_amount' => bcmul($offset, '-1', 2), 'business_date' => $date,
                'policy_snapshot' => ['basis' => 'allocation_reversal', 'rounding' => 'half_up', 'source_batch_id' => $batchId],
            ]);
        }
    }

    /** @return numeric-string */
    private function numeric(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value) && ! is_float($value)) || ! is_numeric((string) $value)) {
            throw new \UnexpectedValueException('Expected an exact numeric value.');
        }

        return bcadd((string) $value, '0', 2);
    }
}
