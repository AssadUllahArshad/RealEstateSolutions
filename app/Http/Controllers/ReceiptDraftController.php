<?php

namespace App\Http\Controllers;

use App\Domain\Configuration\BusinessPolicies;
use App\Domain\Configuration\PolicyDefinitions;
use App\Domain\Export\CsvExport;
use App\Domain\Finance\ReceiptDrafts;
use App\Domain\Finance\ReceiptPosting;
use App\Models\BookingDraft;
use App\Models\BusinessPolicy;
use App\Models\FinancialAccount;
use App\Models\ReceiptDraftEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
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

    public function export(Request $request, ReceiptDrafts $receipts, CsvExport $export): StreamedResponse
    {
        Gate::authorize('finance.receipts.view');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:160'], 'format' => ['nullable', 'in:csv,xlsx']]);
        $query = $receipts->query($request->user());
        if (isset($filters['q']) && $filters['q'] !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->whereHas('customer', fn ($customer) => $customer->whereRaw("name LIKE ? ESCAPE '!'", [$like]));
        }
        $rows = $query->with(['customer:id,name', 'postedReceipt:id,receipt_draft_id,number,state'])->latest('id')->lazy(500)->map(fn ($receipt) => [
            $receipt->id,
            $receipt->customer?->name,
            $receipt->amount,
            $receipt->method,
            $receipt->reported_on,
            $receipt->postedReceipt?->number,
            $receipt->postedReceipt?->state,
        ]);
        $headers = ['ID', 'Customer', 'Amount (PKR)', 'Method', 'Reported on', 'Receipt number', 'Receipt state'];
        $format = $filters['format'] ?? 'csv';

        return $format === 'xlsx'
            ? $export->xlsx('estateflow-receipts.xlsx', $headers, $rows)
            : $export->stream('estateflow-receipts.csv', $headers, $rows);
    }

    public function edit(Request $request, ReceiptDrafts $receipts, ?int $receipt = null): Response|RedirectResponse
    {
        $actor = $request->user();
        $record = $receipt ? $receipts->query($actor)->with(['customer:id,name', 'postedReceipt:id,receipt_draft_id,number,state'])->findOrFail($receipt) : null;
        if ($record?->postedReceipt) {
            return to_route('finance.posted.show', $record->postedReceipt->id);
        }
        $search = $request->validate([
            'customer_q' => ['nullable', 'string', 'max:160'],
            'customer_id' => ['nullable', 'integer'],
            'booking_draft_id' => ['nullable', 'integer'],
            'amount' => ['nullable', 'string', 'regex:/^\d{1,16}(?:\.\d{1,2})?$/D'],
        ]);
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search['customer_q'] ?? '').'%';
        $customers = $receipts->customers($actor)->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orderBy('name')->orderBy('id')->limit(30)->get(['id', 'name']);
        $selectedCustomer = null;
        $selectedDraft = null;
        if (! $record && isset($search['customer_id'])) {
            $selectedCustomer = $receipts->customers($actor)->whereKey($search['customer_id'])->firstOrFail(['id', 'name']);
            if (! $customers->contains('id', $selectedCustomer->id)) {
                $customers->prepend($selectedCustomer);
            }
            if (isset($search['booking_draft_id'])) {
                $selectedDraft = BookingDraft::where('customer_id', $selectedCustomer->id)->whereKey($search['booking_draft_id'])->firstOrFail(['id']);
            }
        }
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
            'prefill' => $record ? null : [
                'customer_id' => $selectedCustomer?->id,
                'booking_draft_id' => $selectedDraft?->id,
                'amount' => $search['amount'] ?? '',
                'financial_account_id' => FinancialAccount::where('active', true)->orderBy('id')->value('id'),
                'method' => array_key_first($methods),
            ],
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
