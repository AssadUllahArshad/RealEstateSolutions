<?php

namespace App\Http\Controllers;

use App\Domain\CRM\Customers;
use App\Models\FollowUp;
use App\Models\User;
use App\Support\BusinessClock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpController extends Controller
{
    public function index(Request $request, Customers $customers, BusinessClock $clock): Response
    {
        $filters = $request->validate(['state' => ['nullable', 'in:open,overdue,today,completed'], 'page' => ['nullable', 'integer', 'min:1']]);
        $state = $filters['state'] ?? 'open';
        $query = FollowUp::whereIn('customer_id', $customers->query($request->user())->select('id'));
        $state === 'completed' ? $query->whereNotNull('completed_at') : $query->whereNull('completed_at');
        if ($state === 'overdue') {
            $query->where('due_date', '<', $clock->today());
        }
        if ($state === 'today') {
            $query->where('due_date', $clock->today());
        }

        return Inertia::render('follow-ups/index', ['followUps' => $query->with('customer:id,name,phone')->orderBy('due_date')->orderBy('id')->paginate(20)->withQueryString(), 'state' => $state, 'today' => $clock->today()]);
    }

    public function store(Request $request, int $customer, Customers $customers): RedirectResponse
    {
        Gate::authorize('customers.manage');
        $data = $request->validate(['note' => ['required', 'string', 'max:1000'], 'due_date' => ['required', 'date_format:Y-m-d'], 'created_by' => ['prohibited'], 'customer_id' => ['prohibited']]);
        DB::transaction(function () use ($request, $customer, $customers, $data) {
            $actor = User::findOrFail($request->user()->id);
            Gate::forUser($actor)->authorize('customers.manage');
            $record = $customers->query($actor)->lockForUpdate()->findOrFail($customer);
            $follow = FollowUp::create([...$data, 'customer_id' => $record->id, 'created_by' => $actor->id]);
            $customers->activity($actor, $record->id, 'follow_up.created', ['follow_up_id' => $follow->id]);
        }, attempts: 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Follow-up scheduled.']);

        return back();
    }

    public function complete(Request $request, FollowUp $followUp, Customers $customers): RedirectResponse
    {
        DB::transaction(function () use ($request, $followUp, $customers) {
            $actor = User::findOrFail($request->user()->id);
            Gate::forUser($actor)->authorize('customers.manage');
            $customers->query($actor)->lockForUpdate()->findOrFail($followUp->customer_id);
            $record = FollowUp::query()->lockForUpdate()->findOrFail($followUp->id);
            if ($record->completed_at !== null) {
                return;
            }
            $record->update(['completed_at' => now(), 'completed_by' => $actor->id]);
            $customers->activity($actor, $record->customer_id, 'follow_up.completed', ['follow_up_id' => $record->id]);
        }, attempts: 3);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Follow-up completed.']);

        return back();
    }
}
