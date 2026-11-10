<?php

namespace Tests\Feature;

use App\Domain\Finance\InstallmentLedger;
use App\Domain\Finance\ReceiptCredit;
use App\Domain\Sales\FinalBookings;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class InstallmentConcurrencyTest extends TestCase
{
    use DatabaseMigrations, FinalBookingFixtures;

    public function test_independent_writers_cannot_double_allocate_and_winning_command_replays(): void
    {
        $f = $this->fixture();
        $booking = app(FinalBookings::class)->confirm($f['agent'], $f['draft']->id, $this->confirmation($f['receipt']->id));
        $accountant = User::factory()->create()->assignRole('accountant');
        $directory = storage_path('framework/testing/installment-race-'.Str::uuid());
        mkdir($directory, 0777, true);
        $processes = [];
        try {
            foreach ([$f['admin']->id, $accountant->id] as $index => $actor) {
                $side = $index === 0 ? 'left' : 'right';
                $process = new Process([PHP_BINARY, base_path('tests/Support/installment-race-worker.php'), $directory, $side, (string) $actor, (string) $booking->id, (string) $f['receipt']->id, (string) Str::uuid()], base_path());
                $process->setTimeout(90)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 45;
            while (! is_file($directory.'/left.ready') || ! is_file($directory.'/right.ready')) {
                foreach ($processes as $index => $process) {
                    $side = $index === 0 ? 'left' : 'right';
                    if (! $process->isRunning() && ! is_file($directory.'/'.$side.'.ready')) {
                        $this->fail('Installment worker exited before barrier.');
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Installment writers did not reach the barrier.');
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
            $states = array_column($results, 'status');
            sort($states);
            $this->assertSame(['conflict', 'saved'], $states);
            $saved = collect($results)->firstWhere('status', 'saved');
            $batch = app(InstallmentLedger::class)->allocate(User::findOrFail($saved['actor']), $booking->id, ['command_key' => $saved['key'], 'version' => 0, 'policy_version' => 1, 'receipt_id' => $f['receipt']->id, 'amount' => '50.01', 'mode' => 'oldest_first', 'rows' => [], 'charge_rows' => [], 'confirmed' => true]);
            $this->assertSame($saved['id'], $batch);
            $this->assertDatabaseCount('installment_batches', 1);
            $this->assertDatabaseCount('installment_allocations', 1);
            $this->assertDatabaseCount('account_movements', 1);
            $this->assertSame('150.02', app(ReceiptCredit::class)->used($f['receipt']->id));
            $this->assertSame('50.01', DB::table('installment_allocations')->sum('amount'));
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
