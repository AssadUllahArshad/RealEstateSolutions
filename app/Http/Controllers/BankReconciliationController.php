<?php

namespace App\Http\Controllers;

use App\Domain\Export\CsvExport;
use App\Domain\Finance\BankReconciliations;
use App\Models\BankReconciliation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BankReconciliationController extends Controller
{
    public function index(Request $request, BankReconciliations $reconciliations): Response
    {
        return Inertia::render('finance/reconciliation', $reconciliations->context($request->user(), $request->query()));
    }

    public function export(Request $request, CsvExport $export): StreamedResponse
    {
        Gate::authorize('finance.accounts.view');
        $filters = $request->validate(['format' => ['nullable', 'in:csv,xlsx']]);
        $rows = BankReconciliation::with(['account:id,code,name', 'preparer:id,name', 'decider:id,name'])->latest('id')->lazy(500)->map(fn ($reconciliation) => [
            $reconciliation->id,
            $reconciliation->account?->code,
            $reconciliation->account?->name,
            $reconciliation->period_start,
            $reconciliation->period_end,
            $reconciliation->statement_opening_balance,
            $reconciliation->statement_closing_balance,
            $reconciliation->state,
            $reconciliation->preparer?->name,
            $reconciliation->decider?->name,
        ]);
        $headers = ['ID', 'Account code', 'Account name', 'Period start', 'Period end', 'Statement opening (PKR)', 'Statement closing (PKR)', 'State', 'Prepared by', 'Decided by'];
        $format = $filters['format'] ?? 'csv';

        return $format === 'xlsx'
            ? $export->xlsx('estateflow-bank-reconciliations.xlsx', $headers, $rows)
            : $export->stream('estateflow-bank-reconciliations.csv', $headers, $rows);
    }

    public function store(Request $request, BankReconciliations $reconciliations): RedirectResponse
    {
        try {
            $reconciliations->prepare($request->user(), $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Bank reconciliation submitted for admin review.']);

        return to_route('finance.reconciliation');
    }

    public function decide(Request $request, int $reconciliation, BankReconciliations $reconciliations): RedirectResponse
    {
        Gate::authorize('finance.accounts.view');
        $record = BankReconciliation::query()->findOrFail($reconciliation);
        try {
            $record = $reconciliations->decide($request->user(), $record, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => $record->state === 'finalized' ? 'Bank reconciliation finalized.' : 'Bank reconciliation rejected.']);

        return to_route('finance.reconciliation');
    }
}
