<?php

namespace App\Http\Controllers;

use App\Domain\Inventory\Holds;
use App\Models\Plot;
use App\Models\PlotHold;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PlotHoldController extends Controller
{
    public function show(Plot $plot): Response
    {
        Gate::authorize('inventory.view');

        return Inertia::render('inventory/hold', [
            'booked' => $plot->activeBooking()->exists(),
            'plot' => $plot->load(['block:id,code,name', 'size:id,label,area_marla'])->only(['id', 'number', 'block', 'size', 'list_price']),
            'active' => PlotHold::where('active_plot_id', $plot->id)->with(['creator:id,name'])->first(),
            'history' => PlotHold::where('plot_id', $plot->id)->with(['creator:id,name', 'releaser:id,name'])->latest('id')->paginate(10),
            'requestKey' => (string) Str::uuid(),
            'canAdminister' => request()->user()->hasAnyRole(['super_admin', 'admin']),
        ]);
    }

    public function store(Request $request, Plot $plot, Holds $holds): RedirectResponse
    {
        Gate::authorize('inventory.view');
        $data = $request->validate([
            'kind' => ['required', 'in:reservation,block'], 'key' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'max:500'],
            'created_by' => ['prohibited'], 'state' => ['prohibited'], 'expires_at' => ['prohibited'], 'customer_id' => ['prohibited'],
        ]);

        return $this->perform(fn () => $holds->execute($request->user(), $plot->id, $data['kind'], $data['key'], $data['reason']));
    }

    public function release(Request $request, PlotHold $hold, Holds $holds): RedirectResponse
    {
        Gate::authorize('inventory.release');
        $data = $request->validate(['key' => ['required', 'uuid'], 'reason' => ['required', 'string', 'max:500'], 'released_by' => ['prohibited'], 'state' => ['prohibited']]);

        return $this->perform(fn () => $holds->execute($request->user(), $hold->plot_id, 'release', $data['key'], $data['reason'], $hold->id));
    }

    private function perform(\Closure $action): RedirectResponse
    {
        try {
            $action();
        } catch (ConflictHttpException $exception) {
            // Inertia refreshes state with an actionable conflict while preserving input/key.
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plot hold updated.']);

        return back();
    }
}
