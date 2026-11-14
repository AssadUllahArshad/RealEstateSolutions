<?php

namespace App\Http\Controllers;

use App\Domain\Export\CsvExport;
use App\Domain\Finance\CustomerRefunds;
use App\Models\Booking;
use App\Models\CustomerRefund;
use App\Models\FinancialAccount;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CustomerRefundController extends Controller
{
    public function index(Request $request, CustomerRefunds $service): Response
    {
        abort_unless($request->user()->can('finance.refunds.view'), 403);
        $bookings = Booking::whereHas('changes', fn ($q) => $q->where('kind', 'cancellation')->where('state', 'approved'))->latest('id')->limit(200)->get()->map(fn ($b) => ['id' => $b->id, 'label' => '#'.$b->id.' · '.$b->snapshot['customer_name'].' · '.$b->snapshot['plot']['number'], 'available' => $service->available($b->id)]);

        return Inertia::render('finance/refunds', ['refunds' => CustomerRefund::latest('id')->paginate(20), 'bookings' => $bookings, 'accounts' => FinancialAccount::where('active', true)->whereIn('type', ['cash', 'bank', 'wallet'])->orderBy('code')->get(['id', 'code', 'name', 'type']), 'businessDate' => app(BusinessClock::class)->today(), 'commandKey' => (string) Str::uuid(), 'decisionKey' => (string) Str::uuid(), 'canPrepare' => $request->user()->can('finance.refunds.prepare') && $request->user()->hasRole('accountant'), 'canApprove' => $request->user()->can('finance.refunds.approve') && $request->user()->hasAnyRole(['admin', 'super_admin'])]);
    }

    public function export(Request $request, CsvExport $export): StreamedResponse
    {
        abort_unless($request->user()->can('finance.refunds.view'), 403);
        $filters = $request->validate(['format' => ['nullable', 'in:csv,xlsx']]);
        $rows = CustomerRefund::latest('id')->lazy(500)->map(fn ($refund) => [$refund->id, $refund->number, $refund->booking_id, $refund->amount, $refund->business_date, $refund->state]);
        $headers = ['ID', 'Number', 'Booking', 'Amount (PKR)', 'Business date', 'State'];
        $format = $filters['format'] ?? 'csv';

        return $format === 'xlsx' ? $export->xlsx('estateflow-customer-refunds.xlsx', $headers, $rows) : $export->stream('estateflow-customer-refunds.csv', $headers, $rows);
    }

    public function store(Request $request, CustomerRefunds $service): RedirectResponse
    {
        try {
            $r = $service->prepare($request->user(), $request->all());
        } catch (ConflictHttpException $e) {
            return back()->withErrors(['conflict' => $e->getMessage()]);
        }Inertia::flash('toast', ['type' => 'success', 'message' => 'Refund '.$r->number.' prepared for approval.']);

        return back();
    }

    public function decide(Request $request, CustomerRefund $refund, CustomerRefunds $service): RedirectResponse
    {
        try {
            $r = $service->decide($request->user(), $refund, $request->all());
        } catch (ConflictHttpException $e) {
            return back()->withErrors(['conflict' => $e->getMessage()]);
        }Inertia::flash('toast', ['type' => 'success', 'message' => 'Refund '.$r->number.' '.$r->state.'.']);

        return back();
    }
}
