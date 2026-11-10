<?php

namespace App\Http\Controllers;

use App\Domain\Finance\ReceiptDrafts;
use App\Domain\Finance\ReceiptPosting;
use App\Models\AccountMovement;
use App\Models\Booking;
use App\Models\FinancialAccount;
use App\Models\OfficeExpense;
use App\Models\OwnerPayout;
use App\Models\CustomerRefund;
use App\Models\CommissionPayment;
use App\Models\PaymentReceiptEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReceiptPostingController extends Controller
{
    public function accept(Request $request, ReceiptPosting $posting, int $receipt): RedirectResponse
    {
        try {
            $record = $posting->accept($request->user(), $receipt, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Receipt issued. Review its recognition and bank-clearance status.']);

        return to_route('finance.posted.show', $record->id);
    }

    public function show(Request $request, ReceiptPosting $posting, int $receipt): Response
    {
        $record = $posting->query($request->user())->with('customer:id,name')->findOrFail($receipt);
        $settings = $posting->settings();
        $canPost = $posting->canPost($request->user());
        $canReverse = $posting->canReverse($request->user());

        return Inertia::render('finance/posted-receipt', ['receipt' => $record, 'reference' => $record->reference,
            'bookingLedgers' => Booking::where('customer_id', $record->customer_id)->latest('id')->limit(20)->get(['id', 'confirmed_on']),
            'movements' => AccountMovement::where('payment_receipt_id', $record->id)->orderBy('id')->get(),
            'history' => PaymentReceiptEvent::where('payment_receipt_id', $record->id)->latest('version')->get()->map(fn ($event) => [...$event->toArray(), 'note' => $canPost || $canReverse ? $event->private_note : null]),
            'posting' => ['settings' => $settings, 'canPost' => $canPost, 'canReverse' => $canReverse, 'clearKey' => (string) Str::uuid(), 'reverseKey' => (string) Str::uuid()],
        ]);
    }

    public function advance(Request $request, ReceiptPosting $posting, int $receipt, string $operation): RedirectResponse
    {
        try {
            $record = $posting->advance($request->user(), $receipt, $operation, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => $operation === 'reverse' ? 'Accounting reversal recorded. No refund was paid.' : 'Cheque clearance recorded.']);

        return to_route('finance.posted.show', $record->id);
    }

    public function movements(Request $request): Response
    {
        Gate::authorize('finance.accounts.view');
        abort_unless(app(ReceiptDrafts::class)->wideAccess($request->user()), 403);
        $filters = $request->validate([
            'account' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $filter = function ($query, string $accountColumn = 'financial_account_id', string $dateColumn = 'business_date') use ($filters) {
            return $query->when($filters['account'] ?? null, fn ($builder, $id) => $builder->where($accountColumn, $id))
                ->when($filters['from'] ?? null, fn ($builder, $date) => $builder->whereDate($dateColumn, '>=', $date))
                ->when($filters['to'] ?? null, fn ($builder, $date) => $builder->whereDate($dateColumn, '<=', $date));
        };
        $receipts = $filter(DB::table('account_movements'), 'account_movements.financial_account_id', 'account_movements.business_date')
            ->join('payment_receipts', 'payment_receipts.id', '=', 'account_movements.payment_receipt_id')
            ->selectRaw("account_movements.id, account_movements.financial_account_id, account_movements.business_date, account_movements.created_at, account_movements.amount, CASE WHEN account_movements.kind = 'receipt_credit' THEN 'inflow' ELSE 'outflow' END AS direction, account_movements.kind AS entry_kind, payment_receipts.number AS source_number, payment_receipts.id AS source_id");
        $expenses = $filter(DB::table('office_expenses'), 'office_expenses.financial_account_id', 'office_expenses.business_date')
            ->selectRaw("office_expenses.id, office_expenses.financial_account_id, office_expenses.business_date, office_expenses.created_at, office_expenses.amount, 'outflow' AS direction, 'office_expense' AS entry_kind, office_expenses.number AS source_number, office_expenses.id AS source_id");
        $payouts = $filter(DB::table('owner_payouts'), 'owner_payouts.financial_account_id', 'owner_payouts.business_date')->where('state', 'approved')
            ->selectRaw("owner_payouts.id, owner_payouts.financial_account_id, owner_payouts.business_date, owner_payouts.decided_at AS created_at, owner_payouts.amount, 'outflow' AS direction, 'owner_payout' AS entry_kind, owner_payouts.number AS source_number, owner_payouts.id AS source_id");
        $refunds = $filter(DB::table('customer_refunds'), 'customer_refunds.financial_account_id', 'customer_refunds.business_date')->where('state', 'approved')
            ->selectRaw("customer_refunds.id, customer_refunds.financial_account_id, customer_refunds.business_date, customer_refunds.decided_at AS created_at, customer_refunds.amount, 'outflow' AS direction, 'customer_refund' AS entry_kind, customer_refunds.number AS source_number, customer_refunds.id AS source_id");
        $commissions = $filter(DB::table('commission_payments'), 'commission_payments.financial_account_id', 'commission_payments.business_date')->where('state', 'approved')
            ->selectRaw("commission_payments.id, commission_payments.financial_account_id, commission_payments.business_date, commission_payments.decided_at AS created_at, commission_payments.amount, 'outflow' AS direction, 'commission_payment' AS entry_kind, commission_payments.number AS source_number, commission_payments.id AS source_id");
        $movements = DB::query()->fromSub($receipts->unionAll($expenses)->unionAll($payouts)->unionAll($refunds)->unionAll($commissions), 'cashbook_entries')
            ->orderByDesc('business_date')->orderByDesc('created_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $receiptTotals = $filter(AccountMovement::query())->selectRaw("COALESCE(SUM(CASE WHEN kind = 'receipt_credit' THEN amount ELSE 0 END), 0) AS inflow, COALESCE(SUM(CASE WHEN kind = 'receipt_reversal' THEN amount ELSE 0 END), 0) AS reversal")->first();
        $expenseTotal = $filter(OfficeExpense::query())->sum('amount');
        $payoutTotal = $filter(OwnerPayout::query())->where('state', 'approved')->sum('amount');
        $refundTotal = $filter(CustomerRefund::query())->where('state', 'approved')->sum('amount');
        $commissionTotal = $filter(CommissionPayment::query())->where('state', 'approved')->sum('amount');
        $inflow = $this->decimal($receiptTotals->inflow);
        $outflow = bcadd(bcadd(bcadd(bcadd($this->decimal($receiptTotals->reversal), $this->decimal($expenseTotal), 2), $this->decimal($payoutTotal), 2), $this->decimal($refundTotal), 2), $this->decimal($commissionTotal), 2);

        return Inertia::render('finance/movements', [
            'movements' => $movements, 'accounts' => FinancialAccount::orderBy('code')->get(['id', 'code', 'name']),
            'filters' => ['account' => isset($filters['account']) ? (string) $filters['account'] : '', 'from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''],
            'summary' => ['inflow' => $inflow, 'outflow' => $outflow, 'net' => bcsub($inflow, $outflow, 2)],
        ]);
    }

    public function exportMovements(Request $request): StreamedResponse
    {
        Gate::authorize('finance.accounts.view');
        abort_unless(app(ReceiptDrafts::class)->wideAccess($request->user()), 403);
        $filters = $request->validate([
            'account' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $apply = function ($query, string $table) use ($filters) {
            return $query->when($filters['account'] ?? null, fn ($builder, $id) => $builder->where($table.'.financial_account_id', $id))
                ->when($filters['from'] ?? null, fn ($builder, $date) => $builder->whereDate($table.'.business_date', '>=', $date))
                ->when($filters['to'] ?? null, fn ($builder, $date) => $builder->whereDate($table.'.business_date', '<=', $date));
        };
        $receipts = $apply(DB::table('account_movements'), 'account_movements')
            ->join('payment_receipts', 'payment_receipts.id', '=', 'account_movements.payment_receipt_id')
            ->join('financial_accounts', 'financial_accounts.id', '=', 'account_movements.financial_account_id')
            ->selectRaw("account_movements.id, account_movements.business_date, account_movements.amount, CASE WHEN account_movements.kind = 'receipt_credit' THEN 'Inflow' ELSE 'Outflow' END AS direction, account_movements.kind AS entry_kind, payment_receipts.number AS source_number, financial_accounts.code AS account_code");
        $expenses = $apply(DB::table('office_expenses'), 'office_expenses')
            ->join('financial_accounts', 'financial_accounts.id', '=', 'office_expenses.financial_account_id')
            ->selectRaw("office_expenses.id, office_expenses.business_date, office_expenses.amount, 'Outflow' AS direction, 'office_expense' AS entry_kind, office_expenses.number AS source_number, financial_accounts.code AS account_code");
        $payouts = $apply(DB::table('owner_payouts'), 'owner_payouts')->where('state', 'approved')->join('financial_accounts', 'financial_accounts.id', '=', 'owner_payouts.financial_account_id')
            ->selectRaw("owner_payouts.id, owner_payouts.business_date, owner_payouts.amount, 'Outflow' AS direction, 'owner_payout' AS entry_kind, owner_payouts.number AS source_number, financial_accounts.code AS account_code");
        $refunds = $apply(DB::table('customer_refunds'), 'customer_refunds')->where('state', 'approved')->join('financial_accounts', 'financial_accounts.id', '=', 'customer_refunds.financial_account_id')
            ->selectRaw("customer_refunds.id, customer_refunds.business_date, customer_refunds.amount, 'Outflow' AS direction, 'customer_refund' AS entry_kind, customer_refunds.number AS source_number, financial_accounts.code AS account_code");
        $commissions = $apply(DB::table('commission_payments'), 'commission_payments')->where('state', 'approved')->join('financial_accounts', 'financial_accounts.id', '=', 'commission_payments.financial_account_id')
            ->selectRaw("commission_payments.id, commission_payments.business_date, commission_payments.amount, 'Outflow' AS direction, 'commission_payment' AS entry_kind, commission_payments.number AS source_number, financial_accounts.code AS account_code");
        $entries = DB::query()->fromSub($receipts->unionAll($expenses)->unionAll($payouts)->unionAll($refunds)->unionAll($commissions), 'cashbook_export')->orderBy('business_date')->orderBy('id');

        return response()->streamDownload(function () use ($entries): void {
            $stream = fopen('php://output', 'wb');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open export stream.');
            }
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['Business date', 'Account', 'Source', 'Entry type', 'Direction', 'Amount (PKR)'], ',', '"', '');
            foreach ($entries->lazy(500) as $entry) {
                fputcsv($stream, [$entry->business_date, $entry->account_code, $entry->source_number, $entry->entry_kind, $entry->direction, $entry->amount], ',', '"', '');
            }
            fclose($stream);
        }, 'estateflow-cashbook.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
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
