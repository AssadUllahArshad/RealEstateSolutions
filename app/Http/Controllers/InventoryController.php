<?php

namespace App\Http\Controllers;

use App\Domain\Export\CsvExport;
use App\Domain\Inventory\Catalogue;
use App\Http\Requests\Inventory\BlockRequest;
use App\Http\Requests\Inventory\PlotRequest;
use App\Http\Requests\Inventory\PlotSizeRequest;
use App\Http\Requests\Inventory\SocietyRequest;
use App\Models\Block;
use App\Models\InventoryEvent;
use App\Models\Plot;
use App\Models\PlotOwner;
use App\Models\PlotSize;
use App\Models\Society;
use App\Models\User;
use App\Rules\Percentage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('inventory.view');
        $filters = $this->filters($request);

        return Inertia::render('inventory/index', [
            'society' => Society::findOrFail(1)->only(['name', 'office_name']),
            'blocks' => Block::orderBy('code')->get(['id', 'code', 'name']),
            'sizes' => PlotSize::orderBy('area_marla')->get(['id', 'label', 'area_marla']),
            'owners' => PlotOwner::orderBy('name')->get(['id', 'name', 'admin_user_id', 'default_percentage']),
            'plots' => $this->query($filters)->paginate(24)->withQueryString(),
            'filters' => $filters,
            'summary' => ['plots' => Plot::count(), 'blocks' => Block::count(), 'sizes' => PlotSize::count()],
            'history' => InventoryEvent::latest('id')->limit(10)->get(['id', 'event', 'subject_type', 'subject_id', 'actor_id', 'created_at']),
        ]);
    }

    public function export(Request $request, CsvExport $export): StreamedResponse
    {
        Gate::authorize('inventory.view');
        Gate::authorize('inventory.export');
        $filters = $this->filters($request);
        $format = $request->validate(['format' => ['nullable', 'in:csv,xlsx']])['format'] ?? 'csv';
        $query = $this->query($filters);
        $rows = $query->lazy(500)->map(fn ($plot) => [
            $plot->block->code,
            $plot->block->name,
            $plot->number,
            ucfirst($plot->category),
            $plot->size->label,
            $plot->size->area_marla,
            $plot->extra_area_sq_ft,
            $plot->list_price ?? '',
            data_get($plot, 'owner.name', 'Unassigned'),
        ]);
        $headers = ['Block code', 'Block name', 'Plot number', 'Category', 'Size label', 'Base area (marla)', 'Extra area (sq ft)', 'Total plot price (PKR)', 'Plot owner'];

        return $format === 'xlsx'
            ? $export->xlsx('estateflow-inventory.xlsx', $headers, $rows)
            : $export->stream('estateflow-inventory.csv', $headers, $rows);
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $filters = [...['q' => '', 'block_id' => '', 'size_id' => '', 'category' => '', 'owner_id' => '', 'hold' => '', 'sort' => 'number', 'view' => 'table'], ...$request->validate([
            'owner_id' => ['nullable', 'integer', 'min:0'],
            'q' => ['nullable', 'string', 'max:64'],
            'block_id' => ['nullable', 'integer', 'exists:blocks,id'],
            'size_id' => ['nullable', 'integer', 'exists:plot_sizes,id'],
            'category' => ['nullable', Rule::in(['residential', 'commercial'])],
            'hold' => ['nullable', Rule::in(['none', 'reservation', 'block', 'booked'])],
            'sort' => ['nullable', Rule::in(['number', 'number_desc', 'newest', 'price', 'price_desc'])],
            'view' => ['nullable', Rule::in(['table', 'grid'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ])];

        return $filters;
    }

    /** @param array<string, mixed> $filters
     * @return Builder<Plot>
     */
    private function query(array $filters): Builder
    {
        $query = Plot::query()->with(['owner:id,name', 'block:id,code,name', 'size:id,label,area_marla', 'activeHold:id,plot_id,kind,state', 'activeBooking:id,plot_id']);
        if (isset($filters['owner_id']) && $filters['owner_id'] !== '') {
            (int) $filters['owner_id'] === 0 ? $query->whereNull('plot_owner_id') : $query->where('plot_owner_id', $filters['owner_id']);
        }
        if (isset($filters['q']) && $filters['q'] !== '') {
            $literal = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], trim($filters['q']));
            $query->whereRaw("number LIKE ? ESCAPE '!'", ['%'.$literal.'%']);
        }
        if (! empty($filters['block_id'])) {
            $query->where('block_id', $filters['block_id']);
        }
        if (! empty($filters['size_id'])) {
            $query->where('plot_size_id', $filters['size_id']);
        }
        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (($filters['hold'] ?? '') === 'none') {
            $query->whereDoesntHave('activeHold')->whereDoesntHave('activeBooking');
        } elseif (($filters['hold'] ?? '') === 'booked') {
            $query->whereHas('activeBooking');
        } elseif (in_array($filters['hold'] ?? '', ['reservation', 'block'], true)) {
            $query->whereHas('activeHold', fn (Builder $hold) => $hold->where('kind', $filters['hold']));
        }
        [$column, $direction] = match ($filters['sort'] ?? 'number') {
            'number_desc' => ['number', 'desc'],
            'newest' => ['id', 'desc'],
            'price' => ['list_price', 'asc'],
            'price_desc' => ['list_price', 'desc'],
            default => ['number', 'asc'],
        };

        return $query->orderBy($column, $direction)->orderBy('id');
    }

    public function setup(): Response
    {
        Gate::authorize('configuration.manage');

        return Inertia::render('inventory/setup', [
            'owners' => PlotOwner::orderBy('name')->get(['id', 'name', 'admin_user_id', 'default_percentage']),
            'admins' => User::role(['admin', 'super_admin'])->orderBy('name')->get(['id', 'name']),
            'society' => Society::findOrFail(1)->only(['name', 'office_name', 'phone', 'email', 'address']),
            'blocks' => Block::orderBy('code')->get(['id', 'code', 'name']),
            'sizes' => PlotSize::orderBy('area_marla')->get(['id', 'label', 'area_marla']),
        ]);
    }

    public function society(SocietyRequest $request, Catalogue $catalogue): RedirectResponse
    {
        $catalogue->society($request->user(), $request->validated());

        return $this->saved('Society profile saved.');
    }

    public function owner(Request $request): RedirectResponse
    {
        Gate::authorize('configuration.manage');
        $data = $request->validate(['name' => ['required', 'string', 'max:120', 'unique:plot_owners,name'], 'admin_user_id' => ['required', 'integer', 'unique:plot_owners,admin_user_id'], 'default_percentage' => ['nullable', new Percentage]]);
        DB::transaction(function () use ($request, $data): void {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('configuration.manage');
            if (PlotOwner::query()->lockForUpdate()->count() >= 2) {
                throw ValidationException::withMessages(['name' => 'Two plot-owner profiles already exist.']);
            }
            User::role(['admin', 'super_admin'])->whereKey($data['admin_user_id'])->lockForUpdate()->firstOrFail();
            $owner = PlotOwner::create([...$data, 'created_by' => $actor->id]);
            InventoryEvent::create(['actor_id' => $actor->id, 'subject_type' => 'plot_owner', 'subject_id' => $owner->id, 'event' => 'plot_owner.created', 'before' => [], 'after' => $owner->only(['name', 'admin_user_id'])]);
        });

        return $this->saved('Plot owner created. Assign plots from the inventory editor.');
    }

    public function updateOwner(Request $request, PlotOwner $owner): RedirectResponse
    {
        Gate::authorize('configuration.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('plot_owners', 'name')->ignore($owner->id)],
            'default_percentage' => ['nullable', new Percentage],
        ]);
        DB::transaction(function () use ($request, $owner, $data): void {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('configuration.manage');
            $record = PlotOwner::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $before = $record->only(['name', 'admin_user_id', 'default_percentage']);
            $record->update($data);
            InventoryEvent::create([
                'actor_id' => $actor->id,
                'subject_type' => 'plot_owner',
                'subject_id' => $record->id,
                'event' => 'plot_owner.updated',
                'before' => $before,
                'after' => $record->only(['name', 'admin_user_id', 'default_percentage']),
            ]);
        });

        return $this->saved('Plot owner profile updated. Existing plot rate snapshots were not changed.');
    }

    public function block(BlockRequest $request, Catalogue $catalogue, ?Block $block = null): RedirectResponse
    {
        $catalogue->block($request->user(), $request->validated(), $block);

        return $this->saved('Block saved.');
    }

    public function size(PlotSizeRequest $request, Catalogue $catalogue, ?PlotSize $size = null): RedirectResponse
    {
        $catalogue->size($request->user(), $request->validated(), $size);

        return $this->saved('Plot size saved.');
    }

    public function plot(PlotRequest $request, Catalogue $catalogue, ?Plot $plot = null): RedirectResponse
    {
        $catalogue->plot($request->user(), $request->validated(), $plot);

        return $this->saved('Plot saved to the catalogue.');
    }

    private function saved(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
