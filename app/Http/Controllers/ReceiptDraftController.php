<?php

namespace App\Http\Controllers;

use App\Domain\Configuration\BusinessPolicies;
use App\Domain\Configuration\PolicyDefinitions;
use App\Domain\Finance\ReceiptDrafts;
use App\Domain\Finance\ReceiptPosting;
use App\Models\BookingDraft;
use App\Models\BusinessPolicy;
use App\Models\FinancialAccount;
use App\Models\ReceiptDraftEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class ReceiptDraftController extends Controller
{
    public function index(Request $request, ReceiptDrafts $receipts): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:160']]);
        $query = $receipts->query($request->user());
        if (isset($filters['q']) && $filters['q'] !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->whereHas('customer', fn ($customer) => $customer->whereRaw("name LIKE ? ESCAPE '!'", [$like]));
        }

        return Inertia::render('finance/receipts', ['receipts' => $query->with(['customer:id,name', 'postedReceipt:id,receipt_draft_id,number,state'])->latest('id')->paginate(20)->withQueryString(), 'q' => $filters['q'] ?? '',
            'canPrepare' => $request->user()->can('finance.receipts.prepare'), 'canViewAccounts' => $request->user()->can('finance.accounts.view')]);
    }

    public function edit(Request $request, ReceiptDrafts $receipts, ?int $receipt = null): Response|RedirectResponse
    {
        $actor = $request->user();
        $record = $receipt ? $receipts->query($actor)->with(['customer:id,name', 'postedReceipt:id,receipt_draft_id,number,state'])->findOrFail($receipt) : null;
        if ($record?->postedReceipt) {
            return to_route('finance.posted.show', $record->postedReceipt->id);
        }
        $search = $request->validate(['customer_q' => ['nullable', 'string', 'max:160']]);
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search['customer_q'] ?? '').'%';
        $customers = $receipts->customers($actor)->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orderBy('name')->orderBy('id')->limit(30)->get(['id', 'name']);
        $policy = BusinessPolicy::query()->whereKey('collections')->first();
        $values = array_replace(app(BusinessPolicies::class)->defaults('collections'), $policy->values ?? []);
        $definition = PolicyDefinitions::all()['collections'];
        $methods = array_intersect_key($definition['fields']['methods']['options'], array_flip($policy ? $values['methods'] : []));

        return Inertia::render('finance/receipt-edit', [
            'receipt' => $record, 'privateDetails' => $record?->only(['reference', 'notes']), 'creationKey' => (string) Str::uuid(),
            'customers' => $customers, 'customerQuery' => $search['customer_q'] ?? '',
            'accounts' => FinancialAccount::where('active', true)->orderBy('code')->get(['id', 'code', 'name', 'type', 'active']),
            'draftOptions' => BookingDraft::whereIn('customer_id', $receipts->customers($actor)->select('id'))->when($record, fn ($query) => $query->where('customer_id', $record->customer_id))->when(! $record, fn ($query) => $query->whereIn('customer_id', $customers->pluck('id')))->latest('id')->limit(100)->get(['id', 'customer_id']),
            'policyVersion' => $policy->version ?? 0, 'methods' => (object) $methods,
            'postingMissing' => array_map(fn ($key) => $definition['fields'][$key]['label'], app(BusinessPolicies::class)->missing('collections', $values)),
            'canPrepare' => $actor->can('finance.receipts.prepare') && ! $record?->postedReceipt, 'canConfigure' => $actor->can('business-settings.manage') && $actor->hasRole('super_admin'),
            'posting' => $record && ! $record->postedReceipt ? ['settings' => app(ReceiptPosting::class)->settings(), 'canPost' => app(ReceiptPosting::class)->canPost($actor), 'commandKey' => (string) Str::uuid()] : null,
            'policyChanged' => $record && $record->policy_snapshot['version'] !== ($policy->version ?? 0),
            'history' => $record ? ReceiptDraftEvent::where('receipt_draft_id', $record->id)->latest('version')->limit(10)->get() : [],
        ]);
    }

    public function store(Request $request, ReceiptDrafts $receipts): RedirectResponse
    {
        return $this->save($request, $receipts);
    }

    public function update(Request $request, ReceiptDrafts $receipts, int $receipt): RedirectResponse
    {
        return $this->save($request, $receipts, $receipt);
    }

    private function save(Request $request, ReceiptDrafts $receipts, ?int $id = null): RedirectResponse
    {
        try {
            $record = $receipts->save($request->user(), $request->all(), $id);
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Receipt preparation saved. Money remains unposted.']);

        return to_route('finance.receipts.edit', $record->id);
    }
}
