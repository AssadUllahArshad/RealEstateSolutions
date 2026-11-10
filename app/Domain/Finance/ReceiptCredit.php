<?php

namespace App\Domain\Finance;

use Illuminate\Support\Facades\DB;

class ReceiptCredit
{
    /** Call with current locks under the shared society/receipt lock before writing.
     * @return numeric-string
     */
    public function used(int $receipt, bool $lock = false): string
    {
        return bcadd($this->usedPrincipal($receipt, $lock), app(ChargePayments::class)->usedForReceipt($receipt, $lock), 2);
    }

    /** Call with current locks under the shared society/receipt lock before writing.
     * @return numeric-string
     */
    public function usedPrincipal(int $receipt, bool $lock = false): string
    {
        $total = '0.00';
        foreach (['booking_allocations', 'installment_allocations'] as $table) {
            $query = DB::table($table)->where('payment_receipt_id', $receipt);
            if ($lock) {
                $query->lockForUpdate();
            }
            foreach ($query->get(['amount']) as $row) {
                $total = bcadd($total, $row->amount, 2);
            }
        }
        $corrections = DB::table('installment_allocation_corrections')
            ->join('installment_allocations', 'installment_allocations.id', '=', 'installment_allocation_corrections.installment_allocation_id')
            ->where('installment_allocations.payment_receipt_id', $receipt)
            ->where('installment_allocation_corrections.state', 'approved');
        if ($lock) {
            $corrections->lockForUpdate();
        }
        foreach ($corrections->get(['installment_allocation_corrections.amount']) as $correction) {
            $total = bcsub($total, $correction->amount, 2);
        }

        return $total;
    }
}
