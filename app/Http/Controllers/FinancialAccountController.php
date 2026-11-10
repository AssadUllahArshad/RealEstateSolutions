<?php

namespace App\Http\Controllers;

use App\Domain\Finance\Accounts;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinancialAccountController extends Controller
{
    public function index(Request $request, ?int $account = null): Response
    {
        Gate::authorize('finance.accounts.view');
        $record = $account ? FinancialAccount::findOrFail($account) : null;

        return Inertia::render('finance/accounts', [
            'accounts' => FinancialAccount::orderByDesc('active')->orderBy('code')->paginate(20)->withQueryString(), 'account' => $record,
            'creationKey' => (string) Str::uuid(), 'canManage' => $request->user()->can('finance.accounts.manage') && $request->user()->hasAnyRole(['super_admin', 'admin']),
            'history' => $record ? FinancialAccountEvent::where('financial_account_id', $record->id)->latest('version')->limit(10)->get() : [],
        ]);
    }

    public function save(Request $request, Accounts $accounts, ?int $account = null): RedirectResponse
    {
        try {
            $record = $accounts->save($request->user(), $request->all(), $account);
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Financial account setup saved.']);

        return to_route('finance.accounts.show', $record->id);
    }
}
