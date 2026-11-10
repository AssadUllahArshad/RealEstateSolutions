<?php

namespace App\Http\Controllers;

use App\Domain\CRM\Customers;
use App\Domain\Finance\InstallmentLedger;
use App\Domain\Sales\BookingTransfers;
use App\Domain\Sales\FinalBookings;
use App\Models\BookingTransfer;
use App\Models\CustomerDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingTransferController extends Controller
{
    public function show(Request $request, FinalBookings $bookings, BookingTransfers $transfers, InstallmentLedger $ledger, int $booking): Response
    {
        $record = $bookings->query($request->user())->findOrFail($booking);
        $history = BookingTransfer::where('booking_id', $record->id)
            ->leftJoin('users as requester', 'requester.id', '=', 'booking_transfers.requested_by')
            ->leftJoin('users as decider', 'decider.id', '=', 'booking_transfers.decided_by')
            ->latest('booking_transfers.id')->get(['booking_transfers.*', 'requester.name as requester_name', 'decider.name as decider_name']);
        $customers = app(Customers::class)->query($request->user())->orderBy('name')->get(['id', 'name', 'cnic', 'address'])->map(function ($customer) {
            $kinds = CustomerDocument::where('customer_id', $customer->id)->pluck('kind')->all();

            return ['id' => $customer->id, 'name' => $customer->name, 'ready' => filled($customer->cnic) && filled($customer->address) && count(array_intersect(['cnic_copy', 'customer_photo'], $kinds)) === 2];
        });
        $active = $transfers->activeParties($record);
        $documents = CustomerDocument::whereIn('customer_id', array_column($active, 'customer_id'))->whereIn('kind', ['signed_transfer_form', 'payment_evidence'])->latest('id')->get(['id', 'kind', 'created_at']);

        return Inertia::render('sales/transfer', [
            'booking' => ['id' => $record->id, 'plot' => $record->snapshot['plot'], 'activePurchasers' => $active, 'outstanding' => $ledger->state($record)['outstanding']],
            'customers' => $customers, 'documents' => $documents, 'transfers' => $history, 'commandKey' => (string) Str::uuid(),
            'canRequest' => $request->user()->can('sales.changes.request'),
            'canApprove' => $request->user()->can('sales.changes.approve') && $request->user()->hasAnyRole(['admin', 'super_admin']),
        ]);
    }

    public function store(Request $request, BookingTransfers $transfers, int $booking): RedirectResponse
    {
        try {
            $transfers->request($request->user(), $booking, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchaser transfer submitted for admin approval.']);

        return to_route('bookings.transfers.show', $booking);
    }

    public function decide(Request $request, BookingTransfers $transfers, int $transfer): RedirectResponse
    {
        try {
            $record = $transfers->decide($request->user(), $transfer, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Purchaser transfer decision recorded.']);

        return to_route('bookings.transfers.show', $record->booking_id);
    }
}
