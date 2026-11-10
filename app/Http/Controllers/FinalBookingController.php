<?php

namespace App\Http\Controllers;

use App\Domain\Sales\FinalBookings;
use App\Models\Booking;
use App\Models\BookingChangeRequest;
use App\Domain\Sales\BookingTransfers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class FinalBookingController extends Controller
{
    public function review(Request $request, FinalBookings $bookings, int $draft): Response|RedirectResponse
    {
        $review = $bookings->review($request->user(), $draft);
        $existing = Booking::where('booking_draft_id', $draft)->first();
        if ($existing) {
            return to_route('bookings.show', $existing->id);
        }

        return Inertia::render('sales/confirm', [...$review, 'commandKey' => (string) Str::uuid()]);
    }

    public function confirm(Request $request, FinalBookings $bookings, int $draft): RedirectResponse
    {
        try {
            $booking = $bookings->confirm($request->user(), $draft, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Booking confirmed. Down payment allocated and plot booked.']);

        return to_route('bookings.show', $booking->id);
    }

    public function show(Request $request, FinalBookings $bookings, int $booking): Response
    {
        $record = $bookings->query($request->user())->findOrFail($booking);
        $allocations = DB::table('booking_allocations')->join('payment_receipts', 'payment_receipts.id', '=', 'booking_allocations.payment_receipt_id')
            ->where('booking_id', $record->id)->get(['payment_receipts.number', 'booking_allocations.amount']);

        $changes = BookingChangeRequest::where('booking_id', $record->id)
            ->leftJoin('users as requester', 'requester.id', '=', 'booking_change_requests.requested_by')
            ->leftJoin('users as decider', 'decider.id', '=', 'booking_change_requests.decided_by')
            ->latest('booking_change_requests.id')
            ->get(['booking_change_requests.*', 'requester.name as requester_name', 'decider.name as decider_name']);

        return Inertia::render('sales/booked', [
            'booking' => $record, 'allocations' => $allocations, 'changes' => $changes,
            'activePurchasers' => app(BookingTransfers::class)->activeParties($record),
            'changeCommandKey' => (string) Str::uuid(),
            'canRequestChange' => $request->user()->can('sales.changes.request'),
            'canApproveChange' => $request->user()->can('sales.changes.approve') && $request->user()->hasAnyRole(['admin', 'super_admin']),
        ]);
    }
}
