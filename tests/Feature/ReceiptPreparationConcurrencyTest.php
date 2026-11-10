<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Domain\Finance\Accounts;
use App\Domain\Inventory\Catalogue;
use App\Models\BusinessPolicy;
use App\Models\ReceiptDraft;
use App\Models\ReceiptDraftEvent;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReceiptPreparationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_independent_mysql_sessions_replay_identical_commands_and_conflict_on_changed_money(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create()->assignRole('super_admin');
        app(Catalogue::class)->society($actor, ['name' => 'DEMO Receipt Race Society']);
        $customer = app(Customers::class)->save($actor, ['name' => 'DEMO Receipt Race Customer', 'creation_key' => (string) Str::uuid()]);
        $account = app(Accounts::class)->save($actor, ['code' => 'RACE', 'name' => 'DEMO Receipt Race Cash', 'type' => 'cash', 'active' => true, 'creation_key' => (string) Str::uuid()]);
        BusinessPolicy::create(['key' => 'collections', 'version' => 1, 'values' => ['methods' => ['cash']], 'updated_by' => $actor->id]);
        foreach ([false, true] as $changed) {
            $key = (string) Str::uuid();
            $directory = storage_path('framework/testing/receipt-race-'.Str::uuid());
            mkdir($directory, 0777, true);
            $processes = [];
            try {
                foreach (['left', 'right'] as $side) {
                    $amount = $changed && $side === 'right' ? '100.02' : '100.01';
                    $process = new Process([PHP_BINARY, base_path('tests/Support/receipt-preparation-race-worker.php'), $directory, $side, (string) $actor->id, (string) $customer->id, (string) $account->id, $key, $amount], base_path());
                    $process->setTimeout(90)->start();
                    $processes[] = $process;
                }
                $deadline = microtime(true) + 45;
                while (! is_file($directory.'/left.ready') || ! is_file($directory.'/right.ready')) {
                    foreach ($processes as $index => $process) {
                        $side = $index === 0 ? 'left' : 'right';
                        if (! $process->isRunning() && ! is_file($directory.'/'.$side.'.ready')) {
                            $this->fail('Receipt race worker exited before the database barrier.');
                        }
                    }
                    if (microtime(true) > $deadline) {
                        $this->fail('Receipt writers did not reach the barrier.');
                    }
                    usleep(10000);
                }
                file_put_contents($directory.'/go', 'go');
                $results = [];
                foreach ($processes as $process) {
                    $this->assertSame(0, $process->wait());
                    $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                }
                $this->assertNotSame($results[0]['connection'], $results[1]['connection']);
                $statuses = array_column($results, 'status');
                sort($statuses);
                $this->assertSame($changed ? ['conflict', 'saved'] : ['saved', 'saved'], $statuses);
                if (! $changed) {
                    $this->assertSame($results[0]['id'], $results[1]['id']);
                }
                $this->assertSame(1, ReceiptDraft::where('creation_key', $key)->count());
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
        $this->assertDatabaseCount('receipt_drafts', 2);
        $this->assertDatabaseCount('receipt_draft_events', 2);
        $this->assertContains(bcadd(ReceiptDraft::orderBy('id')->firstOrFail()->amount, ReceiptDraft::orderByDesc('id')->firstOrFail()->amount, 2), ['200.02', '200.03']);
        foreach (ReceiptDraft::all() as $receipt) {
            $this->assertSame($receipt->amount, ReceiptDraftEvent::where('receipt_draft_id', $receipt->id)->firstOrFail()->details['amount']);
        }
    }
}
