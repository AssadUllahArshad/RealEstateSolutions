<?php

namespace App\Http\Controllers;

use App\Domain\Finance\OwnerPayouts;
use App\Models\FinancialAccount;
use App\Models\OwnerPayout;
use App\Models\PlotOwner;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class OwnerPayoutController extends Controller
{
    public function index(Request $request, OwnerPayouts $service): Response
    {
        abort_unless($request->user()->can('finance.owner-payouts.view'), 403);
        $owners = PlotOwner::orderBy('name')->get(['id', 'name']);
        $balances = $owners->mapWithKeys(fn ($owner) => [(string) $owner->id => $service->payable($owner->id)]);

        return Inertia::render('finance/owner-payouts', [
            'payouts' => OwnerPayout::with(['owner:id,name', 'preparer:id,name', 'decider:id,name'])->latest('id')->paginate(20),
            'owners' => $owners, 'balances' => $balances,
            'accounts' => FinancialAccount::where('active', true)->whereIn('type', ['cash', 'bank', 'wallet'])->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'businessDate' => app(BusinessClock::class)->today(), 'commandKey' => (string) Str::uuid(), 'decisionKey' => (string) Str::uuid(),
            'canPrepare' => $request->user()->can('finance.owner-payouts.prepare') && $request->user()->hasRole('accountant'),
            'canApprove' => $request->user()->can('finance.owner-payouts.approve') && $request->user()->hasAnyRole(['admin', 'super_admin']),
        ]);
    }

    public function store(Request $request, OwnerPayouts $service): RedirectResponse
    {
        try {
            $payout = $service->prepare($request->user(), $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Owner payout '.$payout->number.' prepared for approval.']);

        return back();
    }

    public function decide(Request $request, OwnerPayout $payout, OwnerPayouts $service): RedirectResponse
    {
        try {
            $record = $service->decide($request->user(), $payout, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Owner payout '.$record->number.' '.$record->state.'.']);

        return back();
    }
}
