<?php

namespace App\Http\Controllers;

use App\Domain\Finance\InstallmentLedger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class InstallmentLedgerController extends Controller
{
    public function show(Request $request, InstallmentLedger $ledger, int $booking): Response
    {
        $this->ensureEnabled();

        return Inertia::render('finance/ledger', [...$ledger->context($request->user(), $booking), 'commandKey' => (string) Str::uuid()]);
    }

    public function allocate(Request $request, InstallmentLedger $ledger, int $booking): RedirectResponse
    {
        $this->ensureEnabled();

        try {
            $ledger->allocate($request->user(), $booking, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Recognized credit allocation recorded.']);

        return to_route('bookings.ledger', $booking);
    }

    public function requestCorrection(Request $request, InstallmentLedger $ledger, int $booking): RedirectResponse
    {
        $this->ensureEnabled();

        try {
            $ledger->requestCorrection($request->user(), $booking, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Allocation correction submitted for admin review.']);

        return to_route('bookings.ledger', $booking);
    }

    public function decideCorrection(Request $request, InstallmentLedger $ledger, int $correction): RedirectResponse
    {
        $this->ensureEnabled();

        try {
            $decision = $ledger->decideCorrection($request->user(), $correction, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => $decision->state === 'approved' ? 'Allocation correction approved.' : 'Allocation correction rejected.']);

        return to_route('bookings.ledger', $decision->booking_id);
    }

    private function ensureEnabled(): void
    {
        abort_unless(config('estateflow.post_booking_installments'), 404);
    }
}
