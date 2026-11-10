<?php

namespace Tests\Feature;

use App\Domain\Inventory\Catalogue;
use App\Models\InventoryEvent;
use App\Models\Plot;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_independent_mysql_writers_cannot_create_the_same_plot(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create()->assignRole('super_admin');
        $catalogue = app(Catalogue::class);
        $catalogue->society($actor, ['name' => 'DEMO Race Society']);
        $block = $catalogue->block($actor, ['code' => 'RACE', 'name' => 'DEMO Race Block']);
        $size = $catalogue->size($actor, ['label' => 'DEMO 5', 'area_marla' => '5']);
        $directory = storage_path('framework/testing/inventory-race-'.Str::uuid());
        mkdir($directory, 0777, true);
        $processes = [];
        try {
            foreach (['left', 'right'] as $side) {
                $process = new Process([PHP_BINARY, base_path('tests/Support/inventory-race-worker.php'), $directory, $side, (string) $actor->id, (string) $block->id, (string) $size->id], base_path());
                $process->setTimeout(90)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 45;
            while (! is_file($directory.'/left.ready') || ! is_file($directory.'/right.ready')) {
                foreach ($processes as $index => $process) {
                    $side = $index === 0 ? 'left' : 'right';
                    if (! $process->isRunning() && ! is_file($directory.'/'.$side.'.ready')) {
                        $this->fail('Race worker exited before the database barrier (exit '.$process->getExitCode().').');
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Both independent database sessions must reach the coordination barrier.');
                }
                usleep(10000);
            }
            file_put_contents($directory.'/go', 'go');
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'];
            }
            sort($results);
            $this->assertSame(['conflict', 'created'], $results);
            $this->assertSame(1, Plot::where('number', 'RACE-01')->count());
            $this->assertSame(1, InventoryEvent::where('event', 'plot.created')->count());
        } finally {
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
