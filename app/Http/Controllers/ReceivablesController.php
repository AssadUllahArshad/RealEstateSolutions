<?php

namespace App\Http\Controllers;

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

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('finance.receipts.view');
        $today = app(BusinessClock::class)->today();
        $query = app(Receivables::class)->query($request->user(), $this->filters($request), $today)
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
        return app(Receivables::class)->decimal($value);
    }
}
