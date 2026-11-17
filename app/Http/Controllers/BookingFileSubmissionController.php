<?php

namespace App\Http\Controllers;

use App\Domain\Sales\FinalBookings;
use App\Models\BookingFileSubmission;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class BookingFileSubmissionController extends Controller
{
    public function store(Request $request, FinalBookings $bookings, int $booking): RedirectResponse
    {
        $data = $request->validate([
            'submitted_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.app(BusinessClock::class)->today()],
            'note' => ['nullable', 'string', 'max:1000'],
            'command_key' => ['required', 'uuid'],
        ]);
        DB::transaction(function () use ($request, $bookings, $booking, $data): void {
            $actor = User::query()->lockForUpdate()->findOrFail($request->user()->id);
            Gate::forUser($actor)->authorize('sales.file-submit');
            $record = $bookings->query($actor)->whereKey($booking)->lockForUpdate()->firstOrFail();
            BookingFileSubmission::firstOrCreate(
                ['booking_id' => $record->id],
                ['command_key' => strtolower($data['command_key']), 'submitted_on' => $data['submitted_on'], 'note' => trim($data['note'] ?? '') ?: null, 'submitted_by' => $actor->id],
            );
        }, attempts: 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'File marked as submitted to society.']);

        return back();
    }
}
