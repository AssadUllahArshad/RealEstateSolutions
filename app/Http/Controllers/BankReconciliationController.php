<?php

namespace App\Http\Controllers;

use App\Domain\Finance\BankReconciliations;
use App\Models\BankReconciliation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BankReconciliationController extends Controller
{
    public function index(Request $request, BankReconciliations $reconciliations): Response
    {
        return Inertia::render('finance/reconciliation', $reconciliations->context($request->user(), $request->query()));
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
