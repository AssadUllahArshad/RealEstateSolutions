<?php

namespace App\Http\Controllers;

use App\Domain\Finance\ReceiptDrafts;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReceivablesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('finance.receipts.view');
        $today = app(BusinessClock::class)->today();
        $filters = $this->filters($request);
        $receivables = $this->query($request->user(), $filters, $today);
        $summary = DB::query()->fromSub(clone $receivables, 'filtered_receivables')
            ->selectRaw('COUNT(*) AS bookings, COALESCE(SUM(outstanding), 0) AS outstanding, COALESCE(SUM(overdue), 0) AS overdue, COALESCE(SUM(due_today), 0) AS due_today')
            ->first();

        return Inertia::render('finance/receivables', [
            'businessDate' => $today,
            'filters' => $filters,
            'summary' => [
                'bookings' => (int) ($summary->bookings ?? 0),
                'outstanding' => $this->decimal($summary->outstanding ?? null),
                'overdue' => $this->decimal($summary->overdue ?? null),
                'dueToday' => $this->decimal($summary->due_today ?? null),
            ],
            'receivables' => $receivables->orderByDesc('overdue')->orderBy('next_due_date')->orderBy('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('finance.receipts.view');
        $today = app(BusinessClock::class)->today();
        $query = $this->query($request->user(), $this->filters($request), $today)
            ->orderByDesc('overdue')->orderBy('next_due_date')->orderBy('id');

        return response()->streamDownload(function () use ($query, $today): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open export stream.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Booking ID', 'Customer', 'Plot', 'Confirmed on', 'Next due date', 'Outstanding principal (PKR)', 'Overdue principal (PKR)', 'Due today (PKR)', 'State', 'As of'], ',', '"', '');
            foreach ($query->lazy(500) as $entry) {
                $state = bccomp($this->decimal($entry->overdue), '0', 2) > 0 ? 'Overdue' : (bccomp($this->decimal($entry->due_today), '0', 2) > 0 ? 'Due today' : 'Upcoming');
                fputcsv($stream, [
                    $entry->id,
                    $this->safeCell((string) $entry->customer_name),
                    $this->safeCell((string) $entry->plot_number),
                    $entry->confirmed_on,
                    $entry->next_due_date ?? '',
                    $this->decimal($entry->outstanding),
                    $this->decimal($entry->overdue),
                    $this->decimal($entry->due_today),
                    $state,
                    $today,
                ], ',', '"', '');
            }
            fclose($stream);
        }, 'estateflow-receivables.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** @param array{state:string,search:string} $filters */
    private function query(User $actor, array $filters, string $today): Builder
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

    /** @return array{state:string,search:string} */
    private function filters(Request $request): array
    {
        $filters = $request->validate([
            'state' => ['nullable', 'in:overdue,due_today,outstanding'],
            'search' => ['nullable', 'string', 'max:80'],
        ]);

        return ['state' => $filters['state'] ?? '', 'search' => $filters['search'] ?? ''];
    }

    private function safeCell(string $cell): string
    {
        return preg_match('/^[\s\x00-\x20]*[=+@-]|^[\t\r\n]/u', $cell) ? "'".$cell : $cell;
    }

    /** @return numeric-string */
    private function decimal(mixed $value): string
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
