<?php

namespace App\Domain\Finance;

use App\Models\FinancialAccountOpeningBalance;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use UnexpectedValueException;

class CashbookLedger
{
    /**
     * @return list<array{source_type:string,source_id:int,business_date:string,direction:string,amount:numeric-string,source_number:string}>
     */
    public function entries(int $accountId, string $from, string $to): array
    {
        $queries = [
            DB::table('account_movements as movements')
                ->join('payment_receipts as receipts', 'receipts.id', '=', 'movements.payment_receipt_id')
                ->where('movements.financial_account_id', $accountId)
                ->whereBetween('movements.business_date', [$from, $to])
                ->selectRaw("movements.id as source_id, movements.business_date, CASE WHEN movements.kind = 'receipt_credit' THEN 'inflow' ELSE 'outflow' END as direction, movements.amount, receipts.number as source_number")
                ->selectRaw("'account_movement' as source_type"),
            DB::table('office_expenses')
                ->where('financial_account_id', $accountId)->whereBetween('business_date', [$from, $to])
                ->selectRaw("id as source_id, business_date, 'outflow' as direction, amount, number as source_number, 'office_expense' as source_type"),
            DB::table('owner_payouts')
                ->where('financial_account_id', $accountId)->where('state', 'approved')->whereBetween('business_date', [$from, $to])
                ->selectRaw("id as source_id, business_date, 'outflow' as direction, amount, number as source_number, 'owner_payout' as source_type"),
            DB::table('customer_refunds')
                ->where('financial_account_id', $accountId)->where('state', 'approved')->whereBetween('business_date', [$from, $to])
                ->selectRaw("id as source_id, business_date, 'outflow' as direction, amount, number as source_number, 'customer_refund' as source_type"),
            DB::table('commission_payments')
                ->where('financial_account_id', $accountId)->where('state', 'approved')->whereBetween('business_date', [$from, $to])
                ->selectRaw("id as source_id, business_date, 'outflow' as direction, amount, number as source_number, 'commission_payment' as source_type"),
        ];
        $entries = [];
        foreach ($queries as $query) {
            foreach ($query->get() as $row) {
                $entries[] = [
                    'source_type' => $row->source_type,
                    'source_id' => (int) $row->source_id,
                    'business_date' => $row->business_date,
                    'direction' => $row->direction,
                    'amount' => $this->decimal($row->amount),
                    'source_number' => $row->source_number,
                ];
            }
        }

        usort($entries, fn ($left, $right) => strcmp($left['business_date'], $right['business_date'])
            ?: strcmp($left['source_type'], $right['source_type'])
            ?: $left['source_id'] <=> $right['source_id']);

        return $entries;
    }

    /** @return numeric-string|null */
    public function balanceAtStart(int $accountId, string $date): ?string
    {
        $opening = FinancialAccountOpeningBalance::query()->where('financial_account_id', $accountId)->first();
        if (! $opening || $opening->effective_on > $date) {
            return null;
        }

        $balance = $this->openingBalanceAt($accountId, $date);
        if ($balance === null) {
            return null;
        }
        $previous = CarbonImmutable::parse($date)->subDay()->toDateString();
        if ($previous >= $opening->effective_on) {
            $balance = $this->applyEntries($balance, $this->entries($accountId, $opening->effective_on, $previous));
        }

        return $balance;
    }

    /** @return numeric-string|null */
    public function balanceAtEnd(int $accountId, string $date): ?string
    {
        $opening = FinancialAccountOpeningBalance::query()->where('financial_account_id', $accountId)->first();
        if (! $opening || $opening->effective_on > $date) {
            return null;
        }

        $balance = $this->openingBalanceAt($accountId, $date);
        if ($balance === null) {
            return null;
        }
        $balance = $this->applyEntries($balance, $this->entries($accountId, $opening->effective_on, $date));

        return $balance;
    }

    /** @return numeric-string|null */
    public function openingBalanceAt(int $accountId, string $date): ?string
    {
        $opening = FinancialAccountOpeningBalance::query()->where('financial_account_id', $accountId)->first();
        if (! $opening || $opening->effective_on > $date) {
            return null;
        }

        return $this->addCorrections($opening->id, $date, $this->decimal($opening->amount));
    }

    /** @param list<array{source_type:string,source_id:int,business_date:string,direction:string,amount:numeric-string,source_number:string}> $entries
     * @return numeric-string
     */
    public function movementTotal(array $entries): string
    {
        return $this->applyEntries('0.00', $entries);
    }

    public function firstMovementDate(int $accountId): ?string
    {
        $dates = [
            DB::table('account_movements')->where('financial_account_id', $accountId)->min('business_date'),
            DB::table('office_expenses')->where('financial_account_id', $accountId)->min('business_date'),
            DB::table('owner_payouts')->where('financial_account_id', $accountId)->where('state', 'approved')->min('business_date'),
            DB::table('customer_refunds')->where('financial_account_id', $accountId)->where('state', 'approved')->min('business_date'),
            DB::table('commission_payments')->where('financial_account_id', $accountId)->where('state', 'approved')->min('business_date'),
        ];
        $dates = array_values(array_filter($dates, is_string(...)));

        return $dates === [] ? null : min($dates);
    }

    /**
     * @param  numeric-string  $balance
     * @return numeric-string
     */
    private function addCorrections(int $openingId, string $throughDate, string $balance): string
    {
        $corrections = DB::table('financial_account_opening_corrections')
            ->where('opening_balance_id', $openingId)->where('effective_on', '<=', $throughDate)->get(['amount']);
        foreach ($corrections as $correction) {
            $balance = bcadd($balance, $this->decimal($correction->amount), 2);
        }

        return $balance;
    }

    /**
     * @param  numeric-string  $balance
     * @param  list<array{source_type:string,source_id:int,business_date:string,direction:string,amount:numeric-string,source_number:string}>  $entries
     * @return numeric-string
     */
    private function applyEntries(string $balance, array $entries): string
    {
        foreach ($entries as $entry) {
            $balance = $entry['direction'] === 'inflow'
                ? bcadd($balance, $entry['amount'], 2)
                : bcsub($balance, $entry['amount'], 2);
        }

        return $balance;
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        if ((! is_string($value) && ! is_int($value)) || ! is_numeric((string) $value)) {
            throw new UnexpectedValueException('Expected an exact database decimal.');
        }

        return bcadd((string) $value, '0', 2);
    }
}
