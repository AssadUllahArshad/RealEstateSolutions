<?php

namespace App\Domain\Finance;

use App\Models\BookingCharge;
use App\Models\BookingChargePaymentBatch;
use App\Models\BookingChargePaymentReversal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChargePayments
{
    /** @return numeric-string */
    public function balance(BookingCharge $charge, bool $lock = false): string
    {
        foreach ($this->balances($charge->booking_id, $lock) as $row) {
            if ($row['id'] === $charge->id) {
                return $row['remaining'];
            }
        }

        throw new \LogicException('The locked charge was not found in its booking.');
    }

    /** @return list<array{id:int,number:string,amount:numeric-string,business_date:string,reason:string,paid:numeric-string,waived:numeric-string,remaining:numeric-string}> */
    public function balances(int $bookingId, bool $lock = false): array
    {
        $charges = BookingCharge::query()->where('booking_id', $bookingId)->orderBy('business_date')->orderBy('id');
        $rows = ($lock ? $charges->lockForUpdate() : $charges)->get();
        $balances = [];
        foreach ($rows as $charge) {
            $paidQuery = DB::table('booking_charge_payment_allocations as allocations')
                ->join('booking_charge_payment_batches as batches', 'batches.id', '=', 'allocations.booking_charge_payment_batch_id')
                ->leftJoin('booking_charge_payment_reversals as reversals', 'reversals.booking_charge_payment_batch_id', '=', 'batches.id')
                ->where('allocations.booking_charge_id', $charge->id)
                ->whereNull('reversals.id');
            if ($lock) {
                $paidQuery->lockForUpdate();
            }
            $paid = '0.00';
            foreach ($paidQuery->get(['allocations.amount']) as $payment) {
                $paid = bcadd($paid, $this->decimal($payment->amount), 2);
            }

            $waivers = $charge->waivers()->when($lock, fn ($query) => $query->lockForUpdate())->get(['amount']);
            $waived = '0.00';
            foreach ($waivers as $waiver) {
                $waived = bcadd($waived, $this->decimal($waiver->amount), 2);
            }
            $remaining = bcsub(bcsub($this->decimal($charge->amount), $paid, 2), $waived, 2);
            $balances[] = [
                'id' => $charge->id,
                'number' => $charge->number,
                'amount' => $this->decimal($charge->amount),
                'business_date' => $charge->business_date,
                'reason' => $charge->reason,
                'paid' => $paid,
                'waived' => $waived,
                'remaining' => $remaining,
            ];
        }

        return $balances;
    }

    /** @return numeric-string */
    public function outstanding(int $bookingId, bool $lock = false): string
    {
        $total = '0.00';
        foreach ($this->balances($bookingId, $lock) as $charge) {
            $total = bcadd($total, $charge['remaining'], 2);
        }

        return $total;
    }

    /** @param list<string> $hashes */
    public function allocate(int $bookingId, int $receiptId, int $actorId, int $version, string $amount, string $businessDate, string $commandKey, array $hashes, array $rows): BookingChargePaymentBatch
    {
        $balances = array_column($this->balances($bookingId, true), null, 'id');
        $total = '0.00';
        $allocations = [];
        foreach ($rows as $row) {
            $charge = $balances[$row['charge_id']] ?? null;
            if ($charge === null || bccomp($row['amount'], $charge['remaining'], 2) > 0) {
                throw ValidationException::withMessages(['charge_rows' => 'Each selected charge must belong to this booking and have enough unpaid balance.']);
            }
            $total = bcadd($total, $row['amount'], 2);
            $allocations[] = ['booking_charge_id' => $charge['id'], 'amount' => $row['amount']];
        }
        if (bccomp($total, $amount, 2) !== 0) {
            throw ValidationException::withMessages(['charge_rows' => 'Selected charge amounts must equal the charge portion of this payment.']);
        }

        $batch = BookingChargePaymentBatch::create([
            'booking_id' => $bookingId,
            'payment_receipt_id' => $receiptId,
            'actor_id' => $actorId,
            'version' => $version,
            'amount' => $amount,
            'business_date' => $businessDate,
            'command_key' => $commandKey,
            'payload_hash' => $hashes[0],
        ]);
        foreach ($allocations as $allocation) {
            $batch->allocations()->create($allocation);
        }

        return $batch;
    }

    public function reverseReceipt(int $receiptId, int $actorId, string $businessDate): void
    {
        $batches = BookingChargePaymentBatch::query()->where('payment_receipt_id', $receiptId)
            ->whereDoesntHave('reversal')->lockForUpdate()->get();
        foreach ($batches as $batch) {
            BookingChargePaymentReversal::create([
                'booking_charge_payment_batch_id' => $batch->id,
                'payment_receipt_id' => $receiptId,
                'actor_id' => $actorId,
                'business_date' => $businessDate,
            ]);
        }
    }

    /** @return numeric-string */
    public function usedForReceipt(int $receiptId, bool $lock = false): string
    {
        $query = DB::table('booking_charge_payment_allocations as allocations')
            ->join('booking_charge_payment_batches as batches', 'batches.id', '=', 'allocations.booking_charge_payment_batch_id')
            ->leftJoin('booking_charge_payment_reversals as reversals', 'reversals.booking_charge_payment_batch_id', '=', 'batches.id')
            ->where('batches.payment_receipt_id', $receiptId)
            ->whereNull('reversals.id');
        if ($lock) {
            $query->lockForUpdate();
        }

        $total = '0.00';
        foreach ($query->get(['allocations.amount']) as $allocation) {
            $total = bcadd($total, $this->decimal($allocation->amount), 2);
        }

        return $total;
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        return is_string($value) && is_numeric($value) ? bcadd($value, '0', 2) : '0.00';
    }
}
