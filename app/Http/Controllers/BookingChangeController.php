<?php

namespace App\Http\Controllers;

use App\Domain\Sales\BookingChanges;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingChangeController extends Controller
{
    public function store(Request $request, BookingChanges $changes, int $booking): RedirectResponse
    {
        try {
            $changes->request($request->user(), $booking, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['change' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Booking change request submitted for admin review.']);

        return back();
    }

    public function decide(Request $request, BookingChanges $changes, int $change): RedirectResponse
    {
        $record = $changes->decide($request->user(), $change, $request->all());
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Booking request '.$record->state.'.']);

        return back();
    }
}
