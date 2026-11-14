<?php

namespace App\Http\Controllers;

use App\Domain\Export\CsvExport;
use App\Domain\Finance\Accounts;
use App\Domain\Finance\CashbookLedger;
use App\Domain\Finance\FinancialAccountOpenings;
use App\Models\FinancialAccount;
use App\Models\FinancialAccountEvent;
use App\Models\FinancialAccountOpeningBalance;
use App\Models\FinancialAccountOpeningCorrection;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinancialAccountController extends Controller
{
    public function index(Request $request, ?int $account = null): Response
    {
        Gate::authorize('finance.accounts.view');
        $record = $account ? FinancialAccount::findOrFail($account) : null;
        $openingData = null;
        $bookBalance = null;
        if ($record !== null) {
            $opening = FinancialAccountOpeningBalance::query()
                ->where('financial_account_id', $record->id)
                ->first();
            if ($opening !== null) {
                $ledger = app(CashbookLedger::class);
                $openingData = [
                    ...$opening->only(['id', 'amount', 'effective_on', 'source_note', 'source_reference', 'created_by', 'created_at']),
                    'currentAmount' => $ledger->openingBalanceAt($record->id, app(BusinessClock::class)->today()),
                    'corrections' => FinancialAccountOpeningCorrection::query()->where('opening_balance_id', $opening->id)
                        ->latest('id')->limit(20)->get(['id', 'amount', 'effective_on', 'reason', 'actor_id', 'created_at']),
                ];
                $bookBalance = $ledger->balanceAtEnd($record->id, app(BusinessClock::class)->today());
            }
        }

        return Inertia::render('finance/accounts', [
            'accounts' => FinancialAccount::orderByDesc('active')->orderBy('code')->paginate(20)->withQueryString(), 'account' => $record,
            'creationKey' => (string) Str::uuid(), 'canManage' => $request->user()->can('finance.accounts.manage') && $request->user()->hasAnyRole(['super_admin', 'admin']),
            'history' => $record ? FinancialAccountEvent::where('financial_account_id', $record->id)->latest('version')->limit(10)->get() : [],
            'openingBalance' => $openingData, 'bookBalance' => $bookBalance, 'businessDate' => app(BusinessClock::class)->today(),
            'openingCommandKey' => (string) Str::uuid(), 'correctionCommandKey' => (string) Str::uuid(),
        ]);
    }

    public function export(Request $request, CsvExport $export): StreamedResponse
    {
        Gate::authorize('finance.accounts.view');
        $filters = $request->validate(['format' => ['nullable', 'in:csv,xlsx']]);
        $rows = FinancialAccount::orderByDesc('active')->orderBy('code')->lazy(500)->map(fn ($account) => [
            $account->id,
            $account->code,
            $account->name,
            $account->type,
            $account->active ? 'Active' : 'Inactive',
        ]);
        $headers = ['ID', 'Code', 'Name', 'Type', 'Status'];
        $format = $filters['format'] ?? 'csv';

        return $format === 'xlsx'
            ? $export->xlsx('estateflow-financial-accounts.xlsx', $headers, $rows)
            : $export->stream('estateflow-financial-accounts.csv', $headers, $rows);
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

    public function createOpeningBalance(Request $request, FinancialAccountOpenings $openings, int $account): RedirectResponse
    {
        try {
            $openings->create($request->user(), $account, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Opening balance recorded as an immutable account entry.']);

        return to_route('finance.accounts.show', $account);
    }

    public function correctOpeningBalance(Request $request, FinancialAccountOpenings $openings, int $opening): RedirectResponse
    {
        try {
            $record = $openings->correct($request->user(), $opening, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Linked opening-balance correction recorded.']);
        $accountId = (int) FinancialAccountOpeningBalance::query()->whereKey($record->opening_balance_id)->value('financial_account_id');

        return to_route('finance.accounts.show', $accountId);
    }
}
