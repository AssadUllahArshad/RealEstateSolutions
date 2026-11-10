<?php

namespace Tests\Feature;

use App\Domain\CRM\Customers;
use App\Domain\Finance\Accounts;
use App\Domain\Finance\ReceiptDrafts;
use App\Domain\Finance\ReceiptPosting;
use App\Domain\Inventory\Catalogue;
use App\Models\AccountMovement;
use App\Models\BusinessPolicy;
use App\Models\PaymentReceipt;
use App\Models\User;
use App\Support\BusinessClock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReceiptPostingConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_mysql_races_preserve_numbering_command_replay_and_single_clearance_credit(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create()->assignRole('super_admin');
        $accountant = User::factory()->create()->assignRole('accountant');
        app(Catalogue::class)->society($admin, ['name' => 'DEMO Posting Race']);
        $customer = app(Customers::class)->save($admin, ['name' => 'DEMO Race Payer', 'creation_key' => (string) Str::uuid()]);
        $cash = app(Accounts::class)->save($admin, ['code' => 'RACE-CASH', 'name' => 'DEMO Race cash', 'type' => 'cash', 'active' => true, 'creation_key' => (string) Str::uuid()]);
        $bank = app(Accounts::class)->save($admin, ['code' => 'RACE-BANK', 'name' => 'DEMO Race bank', 'type' => 'bank', 'active' => true, 'creation_key' => (string) Str::uuid()]);
        $date = app(BusinessClock::class)->today();
        BusinessPolicy::create(['key' => 'collections', 'version' => 1, 'updated_by' => $admin->id, 'values' => ['methods' => ['cash', 'cheque'], 'allocation' => 'oldest_first', 'cheque_recognition' => 'cleared', 'overpayment' => 'unallocated']]);
        BusinessPolicy::create(['key' => 'receipt_posting', 'version' => 1, 'updated_by' => $admin->id, 'values' => ['prefix' => 'RACE', 'starting_number' => 1, 'padding' => 4, 'open_from' => $date, 'max_backdate_days' => 1, 'unallocated' => 'allow', 'routing' => 'matching', 'duplicates' => 'global_method']]);
        $drafts = [];
        foreach (['100.01', '100.02', '100.03', '100.04'] as $index => $amount) {
            $drafts[] = app(ReceiptDrafts::class)->save($admin, ['customer_id' => $customer->id, 'financial_account_id' => $index === 3 ? $bank->id : $cash->id, 'amount' => $amount, 'reported_on' => $date, 'method' => $index === 3 ? 'cheque' : 'cash', 'reference' => $index === 3 ? 'DEMO-RACE-CHEQUE' : '', 'policy_version' => 1, 'creation_key' => (string) Str::uuid()]);
        }
        $key = (string) Str::uuid();
        $replay = $this->race([[$admin->id, $drafts[0]->id, 'accept', $key], [$admin->id, $drafts[0]->id, 'accept', $key]], $date);
        $this->assertSame(['saved', 'saved'], array_column($replay, 'status'));
        $this->assertSame($replay[0]['id'], $replay[1]['id']);
        $numbers = $this->race([[$admin->id, $drafts[1]->id, 'accept', (string) Str::uuid()], [$accountant->id, $drafts[2]->id, 'accept', (string) Str::uuid()]], $date);
        $this->assertSame(['saved', 'saved'], array_column($numbers, 'status'));
        $this->assertNotSame($numbers[0]['number'], $numbers[1]['number']);
        $pending = app(ReceiptPosting::class)->accept($admin, $drafts[3]->id, ['command_key' => (string) Str::uuid(), 'expected_version' => 1, 'posting_version' => 1, 'collection_version' => 1, 'business_date' => $date, 'note' => 'DEMO verified cheque deposited but not cleared', 'confirmed' => true, 'stage' => 'deposited']);
        $clears = $this->race([[$admin->id, $pending->id, 'clear', (string) Str::uuid()], [$accountant->id, $pending->id, 'clear', (string) Str::uuid()]], $date);
        $statuses = array_column($clears, 'status');
        sort($statuses);
        $this->assertSame(['conflict', 'saved'], $statuses);
        $this->assertSame(1, AccountMovement::where('payment_receipt_id', $pending->id)->where('kind', 'receipt_credit')->count());
        $bank2 = app(Accounts::class)->save($admin, ['code' => 'RACE-BANK-2', 'name' => 'DEMO Second race bank', 'type' => 'bank', 'active' => true, 'creation_key' => (string) Str::uuid()]);
        $duplicates = [];
        foreach ([$bank, $bank2] as $destination) {
            $duplicates[] = app(ReceiptDrafts::class)->save($admin, ['customer_id' => $customer->id, 'financial_account_id' => $destination->id, 'amount' => '50.05', 'reported_on' => $date, 'method' => 'cheque', 'reference' => 'DEMO-GLOBAL-REFERENCE-RACE', 'policy_version' => 1, 'creation_key' => (string) Str::uuid()]);
        }
        $dedup = $this->race([[$admin->id, $duplicates[0]->id, 'accept-cleared', (string) Str::uuid()], [$accountant->id, $duplicates[1]->id, 'accept-cleared', (string) Str::uuid()]], $date);
        $dedupStates = array_column($dedup, 'status');
        sort($dedupStates);
        $this->assertSame(['rejected', 'saved'], $dedupStates);
        $this->assertDatabaseCount('payment_receipts', 5);
        $this->assertDatabaseCount('account_movements', 5);
        $this->assertDatabaseCount('payment_receipt_events', 6);
        $this->assertDatabaseCount('receipt_posting_commands', 6);
        $this->assertSame(6, DB::table('receipt_series')->value('next_number'));
        $this->assertSame(['RACE-0001', 'RACE-0002', 'RACE-0003', 'RACE-0004', 'RACE-0005'], PaymentReceipt::orderBy('sequence')->pluck('number')->all());
        $this->assertSame('450.15', DB::table('account_movements')->sum('amount'));
    }

    /** @param list<array{int,int,string,string}> $jobs
     * @return list<array<string,mixed>>
     */
    private function race(array $jobs, string $date): array
    {
        $directory = storage_path('framework/testing/posting-race-'.Str::uuid());
        mkdir($directory, 0777, true);
        $processes = [];
        try {
            foreach ($jobs as $index => [$actor, $target, $operation, $key]) {
                $side = $index === 0 ? 'left' : 'right';
                $process = new Process([PHP_BINARY, base_path('tests/Support/receipt-posting-race-worker.php'), $directory, $side, (string) $actor, (string) $target, $operation, $key, $date], base_path());
                $process->setTimeout(90)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 45;
            while (! is_file($directory.'/left.ready') || ! is_file($directory.'/right.ready')) {
                foreach ($processes as $index => $process) {
                    $side = $index === 0 ? 'left' : 'right';
                    if (! $process->isRunning() && ! is_file($directory.'/'.$side.'.ready')) {
                        $this->fail('Posting worker exited before the database barrier.');
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Posting writers failed to reach the barrier.');
                }
                usleep(10000);
            }
            file_put_contents($directory.'/go', 'go');
            $results = [];
            foreach ($processes as $process) {
                $this->assertSame(0, $process->wait(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }
            $this->assertNotSame($results[0]['connection'], $results[1]['connection']);

            return $results;
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
