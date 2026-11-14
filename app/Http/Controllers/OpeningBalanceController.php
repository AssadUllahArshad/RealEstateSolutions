<?php

namespace App\Http\Controllers;

use App\Domain\Finance\OpeningBalances;
use App\Models\Customer;
use App\Models\OpeningCommissionBalance;
use App\Models\OpeningCustomerBalance;
use App\Models\OpeningOwnerBalance;
use App\Models\PlotOwner;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OpeningBalanceController extends Controller
{
    public function index(Request $request, OpeningBalances $openings): Response
    {
        Gate::authorize('finance.accounts.manage');
        abort_unless($request->user()->hasAnyRole(['admin', 'super_admin']), 403);

        return Inertia::render('finance/opening-balances', [
            'cutoverDate' => $openings->cutoverDate(),
            'customers' => OpeningCustomerBalance::query()->with('customer:id,name')->latest('id')->limit(50)->get(),
            'owners' => OpeningOwnerBalance::query()->with('owner:id,name')->latest('id')->limit(50)->get(),
            'commissions' => OpeningCommissionBalance::query()->with('agent:id,name')->latest('id')->limit(50)->get(),
            'customerOptions' => Customer::query()->orderBy('name')->limit(500)->get(['id', 'name']),
            'ownerOptions' => PlotOwner::query()->orderBy('name')->get(['id', 'name']),
            'agentOptions' => User::role(['sales_agent'])->orderBy('name')->get(['id', 'name']),
            'commandKey' => (string) Str::uuid(),
        ]);
    }

    public function storeCustomer(Request $request, OpeningBalances $openings): RedirectResponse
    {
        Gate::authorize('finance.accounts.manage');
        abort_unless($request->user()->hasAnyRole(['admin', 'super_admin']), 403);
        try {
            $openings->createCustomer($request->user(), $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opening customer balance recorded.']);

        return to_route('finance.opening-balances.index');
    }

    public function storeOwner(Request $request, OpeningBalances $openings): RedirectResponse
    {
        Gate::authorize('finance.accounts.manage');
        abort_unless($request->user()->hasAnyRole(['admin', 'super_admin']), 403);
        try {
            $openings->createOwner($request->user(), $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opening owner balance recorded.']);

        return to_route('finance.opening-balances.index');
    }

    public function storeCommission(Request $request, OpeningBalances $openings): RedirectResponse
    {
        Gate::authorize('finance.accounts.manage');
        abort_unless($request->user()->hasAnyRole(['admin', 'super_admin']), 403);
        try {
            $openings->createCommission($request->user(), $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opening commission balance recorded.']);

        return to_route('finance.opening-balances.index');
    }

    public function activate(Request $request, OpeningBalances $openings, string $kind, int $opening): RedirectResponse
    {
        Gate::authorize('finance.accounts.manage');
        abort_unless($request->user()->hasAnyRole(['admin', 'super_admin']), 403);
        try {
            $openings->activate($request->user(), $kind, $opening, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opening balance activated into the live ledger.']);

        return to_route('finance.opening-balances.index');
    }
}
