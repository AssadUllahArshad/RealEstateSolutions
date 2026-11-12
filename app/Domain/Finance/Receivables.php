<?php

namespace App\Domain\Finance;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Receivables
{
    public function installments(User $actor, string $today): Builder
    {
        $wide = app(ReceiptDrafts::class)->wideAccess($actor);
        $allocated = DB::table('installment_allocations')
            ->selectRaw('booking_id, sequence, SUM(amount) AS paid')
            ->groupBy('booking_id', 'sequence');
        $corrected = DB::table('installment_allocation_corrections')
            ->join('installment_allocations', 'installment_allocations.id', '=', 'installment_allocation_corrections.installment_allocation_id')
            ->where('installment_allocation_corrections.state', 'approved')
            ->selectRaw('installment_allocations.booking_id, installment_allocations.sequence, SUM(installment_allocation_corrections.amount) AS amount')
            ->groupBy('installment_allocations.booking_id', 'installment_allocations.sequence');
        $remaining = 'schedule.amount - COALESCE(allocated.paid, 0) + COALESCE(corrected.amount, 0)';

        return DB::table('bookings')
            ->join('customers', 'customers.id', '=', 'bookings.customer_id')
            ->crossJoin(DB::raw("JSON_TABLE(bookings.snapshot, '$.terms.schedule[*]' COLUMNS (sequence INT PATH '$.sequence', due_date DATE PATH '$.due_date', amount DECIMAL(18,2) PATH '$.amount')) AS schedule"))
            ->leftJoinSub($allocated, 'allocated', fn ($join) => $join->on('allocated.booking_id', '=', 'bookings.id')->on('allocated.sequence', '=', 'schedule.sequence'))
            ->leftJoinSub($corrected, 'corrected', fn ($join) => $join->on('corrected.booking_id', '=', 'bookings.id')->on('corrected.sequence', '=', 'schedule.sequence'))
            ->when(! $wide, fn ($query) => $query->where('customers.assigned_to', $actor->id))
            ->selectRaw('bookings.id AS booking_id, schedule.sequence, schedule.due_date, CAST('.$remaining.' AS DECIMAL(18,2)) AS remaining');
    }

    /** @param array{state:string,search:string} $filters */
    public function query(User $actor, array $filters, string $today): Builder
    {
        $wide = app(ReceiptDrafts::class)->wideAccess($actor);
        $allocated = DB::table('installment_allocations')
            ->selectRaw('booking_id, sequence, SUM(amount) AS paid')
            ->groupBy('booking_id', 'sequence');
        $corrected = DB::table('installment_allocation_corrections')
            ->join('installment_allocations', 'installment_allocations.id', '=', 'installment_allocation_corrections.installment_allocation_id')
            ->where('installment_allocation_corrections.state', 'approved')
            ->selectRaw('installment_allocations.booking_id, installment_allocations.sequence, SUM(installment_allocation_corrections.amount) AS amount')
            ->groupBy('installment_allocations.booking_id', 'installment_allocations.sequence');
        $remaining = 'schedule.amount - COALESCE(allocated.paid, 0) + COALESCE(corrected.amount, 0)';
        $grouped = DB::table('bookings')
            ->join('customers', 'customers.id', '=', 'bookings.customer_id')
            ->crossJoin(DB::raw("JSON_TABLE(bookings.snapshot, '$.terms.schedule[*]' COLUMNS (sequence INT PATH '$.sequence', due_date DATE PATH '$.due_date', amount DECIMAL(18,2) PATH '$.amount')) AS schedule"))
            ->leftJoinSub($allocated, 'allocated', fn ($join) => $join->on('allocated.booking_id', '=', 'bookings.id')->on('allocated.sequence', '=', 'schedule.sequence'))
            ->leftJoinSub($corrected, 'corrected', fn ($join) => $join->on('corrected.booking_id', '=', 'bookings.id')->on('corrected.sequence', '=', 'schedule.sequence'))
            ->when(! $wide, fn ($query) => $query->where('customers.assigned_to', $actor->id))
            ->when($filters['search'], function ($query, string $search): void {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($search)).'%';
                $query->where(fn ($nested) => $nested->where('customers.name', 'like', $like)->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(bookings.snapshot, '$.plot.number')) LIKE ?", [$like]));
            })
            ->groupBy('bookings.id', 'bookings.customer_id', 'customers.name', 'bookings.confirmed_on')
            ->selectRaw('bookings.id, bookings.customer_id, customers.name AS customer_name, bookings.confirmed_on')
            ->selectRaw("JSON_UNQUOTE(JSON_EXTRACT(bookings.snapshot, '$.plot.number')) AS plot_number")
            ->selectRaw("CAST(SUM($remaining) AS DECIMAL(18,2)) AS outstanding")
            ->selectRaw("CAST(SUM(CASE WHEN schedule.due_date < ? THEN $remaining ELSE 0 END) AS DECIMAL(18,2)) AS overdue", [$today])
            ->selectRaw("CAST(SUM(CASE WHEN schedule.due_date = ? THEN $remaining ELSE 0 END) AS DECIMAL(18,2)) AS due_today", [$today])
            ->selectRaw("MIN(CASE WHEN $remaining > 0 THEN schedule.due_date END) AS next_due_date");

        $receivables = DB::query()->fromSub($grouped, 'receivables')
            ->when($filters['state'] === 'overdue', fn ($query) => $query->where('overdue', '>', 0))
            ->when($filters['state'] === 'due_today', fn ($query) => $query->where('due_today', '>', 0))
            ->when($filters['state'] === 'outstanding', fn ($query) => $query->where('outstanding', '>', 0));

        return $receivables;
    }

    /** @param array{state:string,search:string} $filters
     * @return array{bookings:int,outstanding:string,overdue:string,dueToday:string}
     */
    public function summary(User $actor, array $filters, string $today): array
    {
        $receivables = $this->query($actor, $filters, $today);
        $summary = DB::query()->fromSub(clone $receivables, 'filtered_receivables')
            ->selectRaw('COUNT(*) AS bookings, COALESCE(SUM(outstanding), 0) AS outstanding, COALESCE(SUM(overdue), 0) AS overdue, COALESCE(SUM(due_today), 0) AS due_today')
            ->first();

        return [
            'bookings' => (int) ($summary->bookings ?? 0),
            'outstanding' => $this->decimal($summary->outstanding ?? null),
            'overdue' => $this->decimal($summary->overdue ?? null),
            'dueToday' => $this->decimal($summary->due_today ?? null),
        ];
    }

    /** @return numeric-string */
    public function decimal(mixed $value): string
    {
        if (is_string($value) && is_numeric($value)) {
            return bcadd($value, '0', 2);
        }
        if (is_int($value) || is_float($value)) {
            return number_format($value, 2, '.', '');
        }

        return '0.00';
    }
}
