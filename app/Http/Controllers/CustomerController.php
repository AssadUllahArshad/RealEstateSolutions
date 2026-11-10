<?php

namespace App\Http\Controllers;

use App\Domain\CRM\Customers;
use App\Models\CustomerDocument;
use App\Models\FollowUp;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CustomerController extends Controller
{
    public function index(Request $request, Customers $customers): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:160'], 'agent' => ['nullable', 'integer'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = $customers->query($request->user());
        if (isset($filters['q']) && $filters['q'] !== '') {
            $literal = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("phone LIKE ? ESCAPE '!'", [$literal])->orWhereRaw("email LIKE ? ESCAPE '!'", [$literal]));
        }
        if (! empty($filters['agent'])) {
            $query->where('assigned_to', $filters['agent']);
        }

        return Inertia::render('customers/index', [
            'customers' => $query->with('agent:id,name')->orderBy('name')->orderBy('id')->paginate(20)->withQueryString(),
            'filters' => ['q' => $filters['q'] ?? '', 'agent' => $filters['agent'] ?? ''],
            'agents' => $this->agents($request), 'creationKey' => (string) Str::uuid(),
            'totalCustomers' => $customers->query($request->user())->count(),
        ]);
    }

    public function show(Request $request, Customers $customers, int $customer): Response
    {
        $record = $customers->query($request->user())->with('agent:id,name')->findOrFail($customer);

        return Inertia::render('customers/show', ['customer' => $record, 'identity' => $record->only(['cnic', 'address']), 'agents' => $this->agents($request),
            'documents' => CustomerDocument::where('customer_id', $customer)->latest('id')->paginate(10, ['id', 'customer_id', 'kind', 'mime', 'bytes', 'created_at'], 'documents_page')->withQueryString(),
            'followUps' => FollowUp::where('customer_id', $customer)->orderByRaw('completed_at IS NOT NULL')->orderBy('due_date')->orderBy('id')->paginate(10),
            'activity' => DB::table('customer_activities')->join('users', 'users.id', '=', 'customer_activities.actor_id')->where('customer_id', $customer)->latest('customer_activities.id')->limit(15)->get(['customer_activities.id', 'event', 'customer_activities.created_at', 'users.name as actor']),
        ]);
    }

    public function store(Request $request, Customers $customers): RedirectResponse
    {
        return $this->save($request, $customers);
    }

    public function update(Request $request, Customers $customers, int $customer): RedirectResponse
    {
        return $this->save($request, $customers, $customer);
    }

    private function save(Request $request, Customers $customers, ?int $id = null): RedirectResponse
    {
        Gate::authorize('customers.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'], 'phone' => ['nullable', 'string', 'max:40'], 'email' => ['nullable', 'email', 'max:160'],
            'assigned_to' => [$request->user()->can('customers.assign') ? 'nullable' : 'prohibited', 'integer', Rule::in($this->agents($request)->pluck('id')->all())],
            'creation_key' => [$id ? 'prohibited' : 'required', 'uuid'], 'created_by' => ['prohibited'],
            'cnic' => ['nullable', 'string', 'regex:/^(?:[0-9]{13}|[0-9]{5}-[0-9]{7}-[0-9])$/D'], 'address' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            $record = $customers->save($request->user(), $data, $id);
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Customer saved.']);

        return to_route('customers.show', $record->id);
    }

    /** @return Collection<int,User> */
    private function agents(Request $request): Collection
    {
        return $request->user()->can('customers.assign') ? User::role(['super_admin', 'admin', 'sales_agent'])->whereNotNull('email_verified_at')->orderBy('name')->get(['id', 'name']) : collect();
    }
}
