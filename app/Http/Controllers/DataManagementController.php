<?php

namespace App\Http\Controllers;

use App\Domain\Administration\OperationalRecordDeletion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DataManagementController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $table = (string) $request->query('resource', 'customers');
        abort_unless(isset(OperationalRecordDeletion::RESOURCES[$table]), 404);
        $definition = OperationalRecordDeletion::RESOURCES[$table];
        $search = trim((string) $request->query('q', ''));
        $query = DB::table($table)->orderByDesc('id');
        if ($search !== '') {
            $query->where(function ($query) use ($definition, $search): void {
                foreach ($definition['search'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}(DB::raw("CAST(`{$column}` AS CHAR)"), 'like', '%'.$search.'%');
                }
            });
        }

        return Inertia::render('administration/data-management', [
            'resources' => collect(OperationalRecordDeletion::RESOURCES)->map(fn ($item, $key) => [
                'key' => $key, 'label' => $item['label'], 'count' => DB::table($key)->count(),
            ])->values(),
            'selected' => $table,
            'q' => $search,
            'records' => $query->paginate(20)->withQueryString()->through(function ($record) use ($definition): array {
                $values = (array) $record;

                return [
                    'id' => $record->id,
                    'title' => (string) ($values[$definition['title']] ?? $record->id),
                    'summary' => collect($values)->except(['id', 'created_at', 'updated_at'])->filter(fn ($value) => is_scalar($value) && $value !== '')->take(4)->all(),
                    'created_at' => $values['created_at'] ?? null,
                ];
            }),
            'recentDeletions' => DB::table('record_deletion_events')->latest('id')->limit(10)->get(),
        ]);
    }

    public function destroy(Request $request, OperationalRecordDeletion $deletion, string $table, int $id): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);
        $data = $request->validate([
            'resource' => ['required', Rule::in(array_keys(OperationalRecordDeletion::RESOURCES))],
            'confirmation' => ['required', 'in:DELETE'],
            'reason' => ['required', 'string', 'min:8', 'max:500'],
        ]);
        abort_unless(hash_equals($table, $data['resource']), 422);

        $result = $deletion->delete($request->user(), $table, $id, $data['reason']);
        Inertia::flash('toast', ['type' => 'success', 'message' => $result['label'].' permanently deleted ('.$result['deleted'].' related rows).']);

        return back();
    }
}
