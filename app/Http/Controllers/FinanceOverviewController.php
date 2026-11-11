<?php

namespace App\Http\Controllers;

use App\Domain\Finance\ReceiptDrafts;
use App\Models\AccountMovement;
use App\Models\CommissionPayment;
use App\Models\CustomerRefund;
use App\Models\FinancialAccount;
use App\Models\OfficeExpense;
use App\Models\OwnerPayout;
use App\Models\PaymentReceipt;
use App\Models\ReceiptDraft;
use App\Support\BusinessClock;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FinanceOverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('finance.accounts.view');
        abort_unless(app(ReceiptDrafts::class)->wideAccess($request->user()), 403);

        $today = app(BusinessClock::class)->today();
        $filters = $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = $filters['from'] ?? substr($today, 0, 8).'01';
        $to = $filters['to'] ?? $today;

        $movementFilter = fn (Builder $query): Builder => $query
            ->whereDate('business_date', '>=', $from)
            ->whereDate('business_date', '<=', $to);
        $expenseTotal = OfficeExpense::query()->whereBetween('business_date', [$from, $to])->sum('amount');
        $inflow = $this->decimal($movementFilter(AccountMovement::query())->where('kind', 'receipt_credit')->sum('amount'));
        $reversals = $this->decimal($movementFilter(AccountMovement::query())->where('kind', 'receipt_reversal')->sum('amount'));
        $expenses = $this->decimal($expenseTotal);
        $refunds = $this->decimal(CustomerRefund::query()->where('state', 'approved')->whereBetween('business_date', [$from, $to])->sum('amount'));
        $ownerPayouts = $this->decimal(OwnerPayout::query()->where('state', 'approved')->whereBetween('business_date', [$from, $to])->sum('amount'));
        $commissionPayments = $this->decimal(CommissionPayment::query()->where('state', 'approved')->whereBetween('business_date', [$from, $to])->sum('amount'));
        $outflow = bcadd(bcadd(bcadd(bcadd($reversals, $expenses, 2), $refunds, 2), $ownerPayouts, 2), $commissionPayments, 2);

        $accounts = FinancialAccount::query()->orderByDesc('active')->orderBy('code')->get(['id', 'code', 'name', 'type', 'active']);
        $movementByAccount = $movementFilter(AccountMovement::query())
            ->groupBy('financial_account_id')
            ->selectRaw('financial_account_id')
            ->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt_credit' THEN amount ELSE -amount END), 0) AS net")
            ->pluck('net', 'financial_account_id');
        $accountOutflowTotals = [];
        foreach ([
            $this->totalsByAccount('office_expenses', $from, $to),
            $this->totalsByAccount('customer_refunds', $from, $to, true),
            $this->totalsByAccount('owner_payouts', $from, $to, true),
            $this->totalsByAccount('commission_payments', $from, $to, true),
        ] as $totals) {
            foreach ($totals as $accountId => $amount) {
                $accountOutflowTotals[$accountId] = bcadd($accountOutflowTotals[$accountId] ?? '0.00', $amount, 2);
            }
        }

        return Inertia::render('finance/overview', [
            'businessDate' => $today,
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => [
                'inflow' => $inflow,
                'reversals' => $reversals,
                'expenses' => $expenses,
                'refunds' => $refunds,
                'ownerPayouts' => $ownerPayouts,
                'commissionPayments' => $commissionPayments,
                'outflow' => $outflow,
                'net' => bcsub($inflow, $outflow, 2),
            ],
            'workflow' => [
                'prepared' => ReceiptDraft::query()->doesntHave('postedReceipt')->count(),
                'pendingClearance' => PaymentReceipt::query()->where('state', 'pending_clearance')->count(),
                'recognized' => PaymentReceipt::query()->where('state', 'recognized')->count(),
            ],
            'accounts' => $accounts->map(function (FinancialAccount $account) use ($movementByAccount, $accountOutflowTotals): array {
                $movement = $this->decimal($movementByAccount->get($account->id));

                return [...$account->toArray(), 'net_movement' => bcsub($movement, $accountOutflowTotals[$account->id] ?? '0.00', 2)];
            }),
        ]);
    }

    /**
     * @return array<int,numeric-string>
     */
    private function totalsByAccount(string $table, string $from, string $to, bool $approvedOnly = false): array
    {
        $query = DB::table($table)->whereBetween('business_date', [$from, $to]);
        if ($approvedOnly) {
            $query->where('state', 'approved');
        }
        $rows = $query->groupBy('financial_account_id')
            ->selectRaw('financial_account_id, SUM(amount) AS total')
            ->get();
        $totals = [];
        foreach ($rows as $row) {
            $totals[(int) $row->financial_account_id] = $this->decimal($row->total);
        }

        return $totals;
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
