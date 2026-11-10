<?php

namespace Tests\Feature;

use App\Domain\Inventory\Catalogue;
use App\Models\InventoryEvent;
use App\Models\Plot;
use App\Models\PlotHold;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class PlotHoldConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_independent_mysql_sessions_enforce_exclusive_hold_block_and_idempotent_replay(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('super_admin');
        $agent = User::factory()->create()->assignRole('sales_agent');
        $catalogue = app(Catalogue::class);
        $catalogue->society($admin, ['name' => 'DEMO Race Society']);
        $block = $catalogue->block($admin, ['code' => 'RACE', 'name' => 'DEMO Race Block']);
        $size = $catalogue->size($admin, ['label' => 'DEMO 5', 'area_marla' => '5']);
        foreach (['hold-hold', 'hold-block', 'same-key', 'independent-plots'] as $scenario) {
            $plot = $catalogue->plot($admin, ['number' => $scenario, 'block_id' => $block->id, 'plot_size_id' => $size->id]);
            $second = $scenario === 'independent-plots' ? $catalogue->plot($admin, ['number' => 'OTHER', 'block_id' => $block->id, 'plot_size_id' => $size->id]) : $plot;
            $key = (string) Str::uuid();
            $directory = storage_path('framework/testing/holds-'.Str::uuid());
            mkdir($directory, 0777, true);
            $processes = [];
            try {
                if ($scenario === 'independent-plots') {
                    DB::beginTransaction();
                    Plot::whereKey($plot->id)->lockForUpdate()->firstOrFail();
                }
                foreach (['left', 'right'] as $side) {
                    $isBlock = $side === 'right' && $scenario === 'hold-block';
                    $actor = $isBlock ? $admin : $agent;
                    $target = $side === 'right' ? $second : $plot;
                    $commandKey = $side === 'left' || $scenario === 'same-key' ? $key : (string) Str::uuid();
                    $process = new Process([PHP_BINARY, base_path('tests/Support/hold-race-worker.php'), $directory, $side, (string) $actor->id, (string) $target->id, $isBlock ? 'block' : 'reservation', $commandKey], base_path());
                    $process->setTimeout(60)->start();
                    $processes[] = $process;
                }
                $deadline = microtime(true) + 35;
                while (! is_file($directory.'/left.ready') || ! is_file($directory.'/right.ready')) {
                    if (microtime(true) > $deadline) {
                        $this->fail('Independent hold writers must reach the barrier.');
                    }
                    usleep(10000);
                }
                file_put_contents($directory.'/go', 'go');
                if ($scenario === 'independent-plots') {
                    // The second plot must finish while the first plot's lock is still held.
                    $this->assertSame(0, $processes[1]->wait());
                    $this->assertTrue($processes[0]->isRunning());
                    $this->assertSame('created', json_decode($processes[1]->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status']);
                    DB::commit();
                }
                $results = [];
                foreach ($processes as $process) {
                    $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                    $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'];
                }
                sort($results);
                $this->assertSame(in_array($scenario, ['same-key', 'independent-plots']) ? ['created', 'created'] : ['conflict', 'created'], $results, $scenario);
                $this->assertSame(1, PlotHold::where('active_plot_id', $plot->id)->count());
                $this->assertSame(1, PlotHold::where('plot_id', $plot->id)->count());
                $this->assertSame(1, InventoryEvent::where('subject_type', 'plot_hold')->where('subject_id', PlotHold::where('plot_id', $plot->id)->firstOrFail()->id)->count());
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                foreach ($processes as $process) {
                    if ($process->isRunning()) {
                        $process->stop(1);
                    }
                }
                foreach (['left.ready', 'right.ready', 'go'] as $file) {
                    if (is_file($directory.'/'.$file)) {
                        unlink($directory.'/'.$file);
                    }
                }
                rmdir($directory);
            }
        }
    }
}
