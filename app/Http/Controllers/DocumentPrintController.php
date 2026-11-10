<?php

namespace App\Http\Controllers;

use App\Domain\Configuration\PolicyDefinitions;
use App\Domain\CRM\Customers;
use App\Domain\Finance\InstallmentLedger;
use App\Domain\Finance\OfficeExpenses;
use App\Domain\Finance\ReceiptCredit;
use App\Domain\Finance\ReceiptPosting;
use App\Domain\Sales\FinalBookings;
use App\Models\CommissionEvent;
use App\Models\CommissionPayment;
use App\Models\CustomerRefund;
use App\Models\OwnerPayout;
use App\Models\PlotOwner;
use App\Models\Society;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DocumentPrintController extends Controller
{
    public function commissionPayment(Request $request, int $payment): Response
    {
        abort_unless($request->user()->can('finance.commissions.view'), 403);
        $record = CommissionPayment::where('state', 'approved')->findOrFail($payment);

        return $this->document('print.commission-payment', ['record' => $record, 'title' => 'Commission payment '.$record->number,
            'issuer' => Society::findOrFail(1)->only(['name', 'office_name', 'phone', 'email', 'address']), 'backUrl' => '/finance/commissions']);
    }

    public function commissionStatement(Request $request, int $agent): Response
    {
        abort_unless($request->user()->can('finance.commissions.view'), 403);
        $filters = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $record = User::role('sales_agent')->findOrFail($agent);
        $events = CommissionEvent::where('agent_id', $agent)->when($filters['from'] ?? null, fn ($q, $d) => $q->where('business_date', '>=', $d))->when($filters['to'] ?? null, fn ($q, $d) => $q->where('business_date', '<=', $d))->orderBy('business_date')->orderBy('id')->get();
        $payments = CommissionPayment::where('agent_id', $agent)->where('state', 'approved')->when($filters['from'] ?? null, fn ($q, $d) => $q->where('business_date', '>=', $d))->when($filters['to'] ?? null, fn ($q, $d) => $q->where('business_date', '<=', $d))->orderBy('business_date')->orderBy('id')->get();
        $numeric = fn ($v) => is_numeric((string) $v) ? bcadd((string) $v, '0', 2) : '0.00';
        $openingEarned = isset($filters['from']) ? CommissionEvent::where('agent_id', $agent)->where('business_date', '<', $filters['from'])->sum('commission_amount') : '0.00';
        $openingPaid = isset($filters['from']) ? CommissionPayment::where('agent_id', $agent)->where('state', 'approved')->where('business_date', '<', $filters['from'])->sum('amount') : '0.00';
        $earned = '0.00';
        foreach ($events as $event) {
            $earned = bcadd($earned, $numeric($event->commission_amount), 2);
        }
        $paid = '0.00';
        foreach ($payments as $payment) {
            $paid = bcadd($paid, $numeric($payment->amount), 2);
        }
        $opening = bcsub($numeric($openingEarned), $numeric($openingPaid), 2);

        return $this->document('print.commission-statement', ['record' => $record, 'events' => $events, 'payments' => $payments, 'opening' => $opening, 'earned' => $earned, 'paid' => $paid, 'closing' => bcsub(bcadd($opening, $earned, 2), $paid, 2), 'filters' => $filters,
            'title' => 'Commission statement · '.$record->name, 'issuer' => Society::findOrFail(1)->only(['name', 'office_name', 'phone', 'email', 'address']), 'backUrl' => '/finance/commissions']);
    }

    public function refund(Request $request, int $refund): Response
    {
        abort_unless($request->user()->can('finance.refunds.view'), 403);
        $record = CustomerRefund::with(['preparer:id,name', 'decider:id,name'])->where('state', 'approved')->findOrFail($refund);

        return $this->document('print.refund', ['record' => $record, 'title' => 'Customer refund '.$record->number,
            'issuer' => Society::findOrFail(1)->only(['name', 'office_name', 'phone', 'email', 'address']), 'backUrl' => '/finance/refunds']);
    }

    public function ownerStatement(Request $request, int $owner): Response
    {
        abort_unless($request->user()->can('reports.owners.view'), 403);
        $filters = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from']]);
        $record = PlotOwner::findOrFail($owner);
        $events = DB::table('owner_entitlement_events')->where('plot_owner_id', $owner)
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->where('business_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->where('business_date', '<=', $date))->orderBy('business_date')->orderBy('id')->get();
        $approved = OwnerPayout::where('plot_owner_id', $owner)->where('state', 'approved')
            ->when($filters['from'] ?? null, fn ($q, $date) => $q->where('business_date', '>=', $date))
            ->when($filters['to'] ?? null, fn ($q, $date) => $q->where('business_date', '<=', $date))->orderBy('business_date')->orderBy('id')->get();
        $openingEntitlement = isset($filters['from']) ? DB::table('owner_entitlement_events')->where('plot_owner_id', $owner)->where('business_date', '<', $filters['from'])->sum('entitlement_amount') : '0.00';
        $openingPaid = isset($filters['from']) ? OwnerPayout::where('plot_owner_id', $owner)->where('state', 'approved')->where('business_date', '<', $filters['from'])->sum('amount') : '0.00';
        $decimal = fn ($value) => is_string($value) && is_numeric($value) ? bcadd($value, '0', 2) : (is_int($value) ? bcadd((string) $value, '0', 2) : '0.00');
        $opening = bcsub($decimal($openingEntitlement), $decimal($openingPaid), 2);
        $earned = '0.00';
        foreach ($events as $event) {
            $earned = bcadd($earned, $decimal($event->entitlement_amount), 2);
        }
        $paid = '0.00';
        foreach ($approved as $payout) {
            $paid = bcadd($paid, $decimal($payout->amount), 2);
        }
        $pending = OwnerPayout::where('plot_owner_id', $owner)->where('state', 'prepared')->orderBy('id')->get()->map(function (OwnerPayout $payout): array {
            $reference = (string) ($payout->reference ?? '');

            return ['business_date' => $payout->business_date, 'number' => $payout->number, 'account_code' => $payout->account_snapshot['code'],
                'amount' => $payout->amount, 'masked_reference' => $reference === '' ? null : '****'.mb_substr($reference, -4)];
        });

        return $this->document('print.owner-statement', ['record' => $record, 'events' => $events, 'approved' => $approved, 'pending' => $pending,
            'opening' => $opening, 'earned' => $earned, 'paid' => $paid, 'closing' => bcsub(bcadd($opening, $earned, 2), $paid, 2),
            'filters' => ['from' => $filters['from'] ?? '', 'to' => $filters['to'] ?? ''], 'title' => 'Owner statement · '.$record->name,
            'issuer' => Society::findOrFail(1)->only(['name', 'office_name', 'phone', 'email', 'address']), 'backUrl' => '/reports/owners?owner_id='.$record->id]);
    }

    public function expense(Request $request, OfficeExpenses $expenses, int $expense): Response
    {
        $record = $expenses->query($request->user())->with('poster:id,name')->findOrFail($expense);
        $issuer = Society::query()->first()?->only(['name', 'office_name', 'phone', 'email', 'address']) ?? [];

        return $this->document('print.expense', [
            'record' => $record, 'title' => 'Office expense '.$record->number, 'issuer' => $issuer,
            'backUrl' => '/finance/expenses',
        ]);
    }

    public function statement(Request $request, InstallmentLedger $ledger, int $booking): Response
    {
        return DB::transaction(function () use ($request, $ledger, $booking) {
            $record = $ledger->query($request->user())->findOrFail($booking);
            $state = $ledger->state($record);
            $downPayments = DB::table('booking_allocations')->join('payment_receipts', 'payment_receipts.id', '=', 'booking_allocations.payment_receipt_id')
                ->where('booking_id', $record->id)->orderBy('booking_allocations.id')->get(['payment_receipts.number', 'booking_allocations.amount']);
            $payments = DB::table('installment_batches')->join('payment_receipts', 'payment_receipts.id', '=', 'installment_batches.payment_receipt_id')
                ->where('booking_id', $record->id)->orderBy('installment_batches.version')->get(['installment_batches.version', 'installment_batches.amount', 'installment_batches.business_date', 'payment_receipts.number']);
            $adjustments = DB::table('installment_allocation_corrections')
                ->join('installment_allocations', 'installment_allocations.id', '=', 'installment_allocation_corrections.installment_allocation_id')
                ->join('payment_receipts', 'payment_receipts.id', '=', 'installment_allocations.payment_receipt_id')
                ->join('users as deciders', 'deciders.id', '=', 'installment_allocation_corrections.decided_by')
                ->where('installment_allocation_corrections.booking_id', $record->id)
                ->where('installment_allocation_corrections.state', 'approved')
                ->orderBy('installment_allocation_corrections.id')
                ->get(['installment_allocation_corrections.id', 'installment_allocation_corrections.amount',
                    'installment_allocation_corrections.business_date', 'installment_allocations.sequence',
                    'payment_receipts.number', 'deciders.name as decider_name']);
            app(Customers::class)->activity($request->user(), $record->customer_id, 'statement.print_previewed', ['booking_id' => $record->id, 'ledger_version' => $state['version']]);

            return $this->document('print.statement', [
                'record' => $record, 'state' => $state, 'downPayments' => $downPayments, 'payments' => $payments, 'adjustments' => $adjustments,
                'title' => 'Principal statement #'.$record->id, 'issuer' => $record->snapshot['society'] ?? [],
                'backUrl' => '/bookings/'.$record->id.'/ledger',
            ]);
        }, attempts: 3);
    }

    public function receipt(Request $request, ReceiptPosting $posting, int $receipt): Response
    {
        return DB::transaction(function () use ($request, $posting, $receipt) {
            $record = $posting->query($request->user())->findOrFail($receipt);
            $allocated = app(ReceiptCredit::class)->used($record->id);
            $recognized = $record->state === 'recognized';
            $status = match ($record->state) {
                'recognized' => 'Recognized receipt', 'pending_clearance' => 'Awaiting bank clearance', default => 'REVERSED — not valid payment credit',
            };
            $method = match ($record->method) {
                'cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'cheque' => 'Cheque', 'mobile_wallet' => 'Mobile wallet', default => 'Other',
            };
            app(Customers::class)->activity($request->user(), $record->customer_id, 'receipt.print_previewed', ['receipt_id' => $record->id, 'version' => $record->version, 'state' => $record->state]);

            return $this->document('print.receipt', [
                'record' => $record, 'title' => 'Receipt '.$record->number, 'status' => $status,
                'method' => $method, 'issuer' => $record->snapshot['issuer'] ?? [],
                'allocated' => bcadd($allocated, '0', 2),
                'unallocated' => $recognized ? bcsub($record->amount, $allocated, 2) : '0.00',
                'recognized' => $recognized, 'backUrl' => '/finance/posted/'.$record->id,
            ]);
        }, attempts: 3);
    }

    public function booking(Request $request, FinalBookings $bookings, int $booking): Response
    {
        return DB::transaction(function () use ($request, $bookings, $booking) {
            $record = $bookings->query($request->user())->findOrFail($booking);
            $allocations = DB::table('booking_allocations')->join('payment_receipts', 'payment_receipts.id', '=', 'booking_allocations.payment_receipt_id')
                ->where('booking_id', $record->id)->orderBy('booking_allocations.id')->get(['payment_receipts.number', 'booking_allocations.amount']);
            app(Customers::class)->activity($request->user(), $record->customer_id, 'booking.print_previewed', ['booking_id' => $record->id]);

            return $this->document('print.booking', [
                'record' => $record, 'title' => 'Booking confirmation #'.$record->id,
                'allocations' => $allocations, 'issuer' => $record->snapshot['society'] ?? [],
                'documentLabels' => PolicyDefinitions::all()['booking']['fields']['required_documents']['options'],
                'backUrl' => '/bookings/'.$record->id,
            ]);
        }, attempts: 3);
    }

    /** @param array<string,mixed> $data */
    private function document(string $view, array $data): Response
    {
        return response()->view($view, [...$data, 'generatedAt' => CarbonImmutable::now('Asia/Karachi')->format('Y-m-d H:i:s').' Asia/Karachi'], 200, [
            'Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff', 'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'no-referrer', 'X-Robots-Tag' => 'noindex, nofollow, noarchive',
            'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'self'; script-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'none'",
        ]);
    }
}
