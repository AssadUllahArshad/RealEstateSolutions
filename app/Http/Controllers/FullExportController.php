<?php

namespace App\Http\Controllers;

use App\Domain\Export\CsvExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class FullExportController extends Controller
{
    /** Framework-internal tables excluded from the full export. */
    private const EXCLUDED_TABLES = [
        'password_reset_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations',
    ];

    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($request->user()->hasRole('super_admin'), 403);

        $tables = Schema::getTableListing(null, false);
        $tables = array_values(array_filter($tables, fn (string $table) => ! in_array($table, self::EXCLUDED_TABLES, true)));
        sort($tables);

        $tempFile = tempnam(sys_get_temp_dir(), 'estateflow-full-export-');
        if ($tempFile === false) {
            throw new \RuntimeException('Unable to create temporary export file.');
        }

        $zip = new ZipArchive;
        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($tempFile);
            throw new \RuntimeException('Unable to create export archive.');
        }

        foreach ($tables as $table) {
            $zip->addFromString($table.'.csv', $this->tableToCsv($table));
        }

        $zip->close();

        Log::info('Full data export', ['actor_id' => $request->user()->id, 'tables' => count($tables)]);

        return response()->streamDownload(function () use ($tempFile): void {
            readfile($tempFile);
            @unlink($tempFile);
        }, 'estateflow-full-export-'.now()->format('Y-m-d-His').'.zip', [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function tableToCsv(string $table): string
    {
        $columns = Schema::getColumnListing($table);
        $output = fopen('php://temp', 'r+b');
        if ($output === false) {
            throw new \RuntimeException('Unable to open CSV buffer.');
        }

        fwrite($output, "\xEF\xBB\xBF");
        fputcsv($output, $columns, ',', '"', '');

        foreach (DB::table($table)->cursor() as $row) {
            $values = [];
            foreach ($columns as $column) {
                $values[] = CsvExport::escapeCell($row->{$column} ?? null);
            }
            fputcsv($output, $values, ',', '"', '');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv === false ? '' : $csv;
    }
}
