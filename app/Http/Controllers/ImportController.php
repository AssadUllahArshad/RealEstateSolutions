<?php

namespace App\Http\Controllers;

use App\Domain\Import\ImportService;
use App\Domain\Import\ImportTemplates;
use App\Models\ImportBatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('import.run');
        $batches = ImportBatch::query()->with('actor:id,name')->latest('id')->limit(20)->get();

        return Inertia::render('imports/index', [
            'templates' => collect(ImportTemplates::all())->map(fn ($definition, $key) => ['key' => $key, 'label' => $definition['label'], 'headers' => $definition['headers']])->values(),
            'batches' => $batches,
        ]);
    }

    public function template(Request $request, string $template): StreamedResponse
    {
        Gate::authorize('import.run');
        $definition = ImportTemplates::get($template);
        abort_unless($definition !== null, 404);

        $csv = "\xEF\xBB\xBF".implode(',', $definition['headers'])."\n".implode(',', $definition['example'])."\n";

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'estateflow-import-'.$template.'-v'.ImportTemplates::VERSION.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function dryRun(Request $request, ImportService $service): RedirectResponse
    {
        Gate::authorize('import.run');
        $data = $request->validate([
            'template' => ['required', Rule::in(array_keys(ImportTemplates::all()))],
            'file' => ['required', 'file', 'max:5120', 'mimes:csv,txt'],
        ]);

        $rows = $this->parseCsv($request->file('file')->getRealPath(), $data['template']);
        $batch = $service->dryRun($request->user(), $data['template'], $rows, $request->file('file')->getClientOriginalName());

        return to_route('imports.show', $batch->id);
    }

    public function show(Request $request, int $batch): Response
    {
        Gate::authorize('import.run');
        $record = ImportBatch::query()->with(['actor:id,name', 'rows' => fn ($query) => $query->orderBy('row_number')])->findOrFail($batch);

        return Inertia::render('imports/show', [
            'batch' => $record,
            'template' => ImportTemplates::get($record->template),
        ]);
    }

    public function commit(Request $request, ImportService $service, int $batch): RedirectResponse
    {
        Gate::authorize('import.run');
        $service->commit($request->user(), $batch);

        return to_route('imports.show', $batch);
    }

    /** @return list<array<string,string>> */
    private function parseCsv(string $path, string $template): array
    {
        $definition = ImportTemplates::get($template);
        abort_unless($definition !== null, 404);

        $handle = fopen($path, 'rb');
        abort_unless($handle !== false, 422);

        $header = fgetcsv($handle, 0, ',', '"', '');
        if ($header === false) {
            fclose($handle);
            abort(422, 'The CSV file is empty.');
        }
        $header = array_map(fn ($value) => trim((string) $value), $header);
        // Strip UTF-8 BOM from the first header cell.
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);

        $expected = $definition['headers'];
        if ($header !== $expected) {
            fclose($handle);
            abort(422, 'The CSV header does not match the template. Expected: '.implode(', ', $expected));
        }

        $rows = [];
        while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if (count($line) === 1 && trim((string) $line[0]) === '') {
                continue; // skip blank lines
            }
            $row = [];
            foreach ($expected as $index => $column) {
                $row[$column] = isset($line[$index]) ? trim((string) $line[$index]) : '';
            }
            $rows[] = $row;
        }
        fclose($handle);

        if ($rows === []) {
            abort(422, 'The CSV file contains no data rows.');
        }
        if (count($rows) > 5000) {
            abort(422, 'The CSV file exceeds the 5000 row limit.');
        }

        return $rows;
    }
}
