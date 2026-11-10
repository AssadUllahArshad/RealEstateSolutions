<?php

namespace App\Http\Controllers;

use App\Domain\CRM\Customers;
use App\Domain\Sales\BookingDrafts;
use App\Models\Block;
use App\Models\FollowUp;
use App\Models\InventoryEvent;
use App\Models\Plot;
use App\Models\PlotHold;
use App\Models\Society;
use App\Support\BusinessClock;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function __invoke(Request $request, Customers $customers, BusinessClock $clock): Response
    {
        $inventory = $request->user()->can('inventory.view');
        $crm = $request->user()->can('customers.view');
        $followQuery = $crm ? FollowUp::whereIn('customer_id', $customers->query($request->user())->select('id')) : null;

        return Inertia::render('workspace', [
            'businessDate' => $clock->today(), 'timezone' => config('estateflow.business_timezone'), 'currency' => config('estateflow.currency'),
            'society' => Society::find(1)?->only(['name', 'office_name']),
            'stats' => ['drafts' => $inventory && $crm && $request->user()->can('sales.drafts') ? app(BookingDrafts::class)->query($request->user())->count() : null,
                'plots' => $inventory ? Plot::count() : null, 'held' => $inventory ? PlotHold::where('state', 'active')->where('kind', 'reservation')->count() : null,
                'blocked' => $inventory ? PlotHold::where('state', 'active')->where('kind', 'block')->count() : null, 'unheld' => $inventory ? Plot::whereDoesntHave('activeHold')->whereDoesntHave('activeBooking')->count() : null,
                'customers' => $crm ? $customers->query($request->user())->count() : null, 'followUps' => $crm ? (clone $followQuery)->whereNull('completed_at')->count() : null,
                'overdue' => $crm ? (clone $followQuery)->whereNull('completed_at')->where('due_date', '<', $clock->today())->count() : null],
            'blocks' => $inventory ? Block::withCount('plots')->orderBy('code')->get(['id', 'code', 'name']) : [],
            'activity' => $inventory ? InventoryEvent::latest('id')->limit(6)->get(['id', 'event', 'subject_type', 'subject_id', 'created_at']) : [],
            'tasks' => $crm ? (clone $followQuery)->whereNull('completed_at')->with('customer:id,name')->orderBy('due_date')->limit(5)->get() : [],
        ]);
    }
}
