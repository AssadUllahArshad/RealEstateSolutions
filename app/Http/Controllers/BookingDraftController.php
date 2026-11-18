<?php

namespace App\Http\Controllers;

use App\Domain\CRM\Customers;
use App\Domain\Export\CsvExport;
use App\Domain\Sales\BookingDrafts;
use App\Domain\Sales\BookingPreparation;
use App\Domain\Sales\DraftPurchasers;
use App\Models\BookingDraftEvent;
use App\Models\DraftPurchaserEvent;
use App\Models\Plot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingDraftController extends Controller
{
    public function index(Request $request, BookingDrafts $drafts): Response
    {
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:160']]);
        $query = $drafts->query($request->user());
        if (isset($filters['q']) && $filters['q'] !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->whereHas('customer', fn ($c) => $c->whereRaw("name LIKE ? ESCAPE '!'", [$like]))->orWhereHas('plot', fn ($p) => $p->whereRaw("number LIKE ? ESCAPE '!'", [$like])));
        }

        return Inertia::render('sales/index', ['drafts' => $query->with(['customer:id,name', 'plot.block:id,code', 'booking:id,booking_draft_id'])->latest('id')->paginate(20)->withQueryString(), 'q' => $filters['q'] ?? '']);
    }

    public function export(Request $request, BookingDrafts $drafts, CsvExport $export): StreamedResponse
    {
        Gate::authorize('sales.drafts');
        Gate::authorize('inventory.view');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:160'], 'format' => ['nullable', 'in:csv,xlsx']]);
        $query = $drafts->query($request->user());
        if (isset($filters['q']) && $filters['q'] !== '') {
            $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['q']).'%';
            $query->where(fn ($q) => $q->whereHas('customer', fn ($c) => $c->whereRaw("name LIKE ? ESCAPE '!'", [$like]))->orWhereHas('plot', fn ($p) => $p->whereRaw("number LIKE ? ESCAPE '!'", [$like])));
        }
        $rows = $query->with(['customer:id,name', 'plot.block:id,code', 'booking:id,booking_draft_id'])->latest('id')->lazy(500)->map(fn ($draft) => [
            $draft->id,
            $draft->customer?->name,
            ($draft->plot?->block->code ?? '').' / '.($draft->plot->number ?? ''),
            $draft->quoted_price,
            $draft->booking ? 'Confirmed' : 'Draft',
            $draft->created_at?->toDateString(),
        ]);
        $headers = ['ID', 'Customer', 'Plot', 'Quoted price (PKR)', 'Status', 'Created on'];
        $format = $filters['format'] ?? 'csv';

        return $format === 'xlsx'
            ? $export->xlsx('estateflow-sales-drafts.xlsx', $headers, $rows)
            : $export->stream('estateflow-sales-drafts.csv', $headers, $rows);
    }

    public function edit(Request $request, BookingDrafts $drafts, ?int $draft = null): Response|RedirectResponse
    {
        Gate::authorize('sales.drafts');
        Gate::authorize('inventory.view');
        $record = $draft ? $drafts->query($request->user())->with(['customer:id,name', 'plot.block:id,code', 'plot.size:id,label,area_marla', 'plot.activeHold'])->findOrFail($draft) : null;
        if ($record?->booking) {
            return to_route('bookings.show', $record->booking->id);
        }
        $search = $request->validate(['customer_q' => ['nullable', 'string', 'max:160'], 'plot_q' => ['nullable', 'string', 'max:160']]);
        $literal = fn (string $value) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';

        return Inertia::render('sales/edit', [
            'draft' => $record, 'creationKey' => (string) Str::uuid(),
            'preparation' => $record ? app(BookingPreparation::class)->forDraft($request->user(), $record->id) : null,
            'purchasers' => $record ? app(DraftPurchasers::class)->listing($request->user(), $record->id) : [],
            'purchaserHistory' => $record ? DraftPurchaserEvent::where('booking_draft_id', $record->id)->latest('version')->limit(10)->get()->map(fn ($event) => ['version' => $event->version, 'actor_id' => $event->actor_id, 'count' => count($event->after), 'created_at' => $event->created_at->toIso8601String()]) : [],
            'history' => $record ? BookingDraftEvent::where('booking_draft_id', $record->id)->latest('version')->paginate(10)->withQueryString() : null,
            'customers' => app(Customers::class)->query($request->user())->whereRaw("name LIKE ? ESCAPE '!'", [$literal($search['customer_q'] ?? '')])->orderBy('name')->orderBy('id')->limit(30)->get(['id', 'name']),
            'plots' => Plot::with(['block:id,code', 'size:id,label,area_marla', 'activeHold:id,plot_id,kind'])->whereDoesntHave('activeBooking')->whereRaw("number LIKE ? ESCAPE '!'", [$literal($search['plot_q'] ?? '')])->orderBy('number')->orderBy('id')->limit(30)->get(['id', 'block_id', 'plot_size_id', 'number', 'category', 'extra_area_sq_ft', 'list_price']),
            'lookup' => ['customer_q' => $search['customer_q'] ?? '', 'plot_q' => $search['plot_q'] ?? ''],
        ]);
    }

    public function store(Request $request, BookingDrafts $drafts): RedirectResponse
    {
        return $this->save($request, $drafts);
    }

    public function update(Request $request, BookingDrafts $drafts, int $draft): RedirectResponse
    {
        return $this->save($request, $drafts, $draft);
    }

    public function purchasers(Request $request, DraftPurchasers $purchasers, int $draft): RedirectResponse
    {
        try {
            $purchasers->save($request->user(), $draft, $request->all());
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['purchasers_conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proposed purchasers saved.']);

        return to_route('sales.edit', $draft);
    }

    private function save(Request $request, BookingDrafts $drafts, ?int $id = null): RedirectResponse
    {
        try {
            $record = $drafts->save($request->user(), $request->all(), $id);
        } catch (ConflictHttpException $exception) {
            return back()->withErrors(['conflict' => $exception->getMessage()]);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Booking draft saved.']);

        return to_route('sales.edit', $record->id);
    }
}
