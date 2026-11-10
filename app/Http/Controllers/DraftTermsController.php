<?php

namespace App\Http\Controllers;

use App\Domain\Sales\DraftTerms;
use App\Models\DraftTermEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class DraftTermsController extends Controller
{
    public function show(Request $request, DraftTerms $terms, int $draft): Response|RedirectResponse
    {
        $context = $terms->context($request->user(), $draft);
        if ($context['draft']->booking) {
            return to_route('bookings.show', $context['draft']->booking->id);
        }

        return Inertia::render('sales/terms', [...$context,
            'canConfigure' => $request->user()->can('business-settings.manage') && $request->user()->hasRole('super_admin'),
            'history' => DraftTermEvent::where('booking_draft_id', $draft)->latest('version')->limit(10)->get()->map(fn ($event) => ['version' => $event->version, 'actor_id' => $event->actor_id, 'net' => $event->snapshot['net'], 'count' => count($event->snapshot['schedule']), 'created_at' => $event->created_at->toIso8601String()]),
        ]);
    }

    public function save(Request $request, DraftTerms $terms, int $draft): RedirectResponse
    {
        try {
            $terms->save($request->user(), $draft, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proposed terms calculated and saved.']);

        return to_route('sales.terms', $draft);
    }
}
