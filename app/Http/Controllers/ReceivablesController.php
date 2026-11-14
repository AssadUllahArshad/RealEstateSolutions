<?php

namespace App\Http\Controllers;

use App\Domain\Export\CsvExport;
use App\Domain\Finance\Receivables;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
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
        $receivables = app(Receivables::class)->query($request->user(), $filters, $today);
        $summary = app(Receivables::class)->summary($request->user(), $filters, $today);

        return Inertia::render('finance/receivables', [
            'businessDate' => $today,
            'filters' => $filters,
            'summary' => $summary,
            'receivables' => $receivables->orderByDesc('overdue')->orderBy('next_due_date')->orderBy('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function export(Request $request, CsvExport $export): StreamedResponse
    {
        Gate::authorize('finance.receipts.view');
        $today = app(BusinessClock::class)->today();
        $filters = $this->filters($request);
        $format = $request->validate(['format' => ['nullable', 'in:csv,xlsx']])['format'] ?? 'csv';
        $query = app(Receivables::class)->query($request->user(), $filters, $today)
            ->orderByDesc('overdue')->orderBy('next_due_date')->orderBy('id');
        $rows = $query->lazy(500)->map(function ($entry) use ($today) {
            $state = bccomp($this->decimal($entry->overdue), '0', 2) > 0 ? 'Overdue' : (bccomp($this->decimal($entry->due_today), '0', 2) > 0 ? 'Due today' : 'Upcoming');

            return [
                $entry->id,
                $entry->customer_name,
                $entry->plot_number,
                $entry->confirmed_on,
                $entry->next_due_date ?? '',
                $this->decimal($entry->outstanding),
                $this->decimal($entry->overdue),
                $this->decimal($entry->due_today),
                $state,
                $today,
            ];
        });
        $headers = ['Booking ID', 'Customer', 'Plot', 'Confirmed on', 'Next due date', 'Outstanding principal (PKR)', 'Overdue principal (PKR)', 'Due today (PKR)', 'State', 'As of'];

        return $format === 'xlsx'
            ? $export->xlsx('estateflow-receivables.xlsx', $headers, $rows)
            : $export->stream('estateflow-receivables.csv', $headers, $rows);
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

    /** @return numeric-string */
    private function decimal(mixed $value): string
    {
        return app(Receivables::class)->decimal($value);
    }
}
