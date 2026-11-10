<?php

namespace Tests\Feature;

use App\Domain\Inventory\Catalogue;
use App\Domain\Sales\BookingDrafts;
use App\Domain\Sales\DraftPurchasers;
use App\Domain\Sales\DraftTerms;
use App\Models\DraftTerm;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\FinalBookingFixtures;
use Tests\TestCase;

class FinalBookingConcurrencyTest extends TestCase
{
    use DatabaseMigrations, FinalBookingFixtures;

    public function test_independent_connections_preserve_replay_plot_exclusivity_and_credit_limits(): void
    {
        $f = $this->fixture('250.02');
        $key = (string) Str::uuid();
        $job = [$f['agent']->id, $f['draft']->id, $f['receipt']->id, $key];
        $replay = $this->race([$job, $job]);
        $this->assertSame(['saved', 'saved'], array_column($replay, 'status'));
        $this->assertSame($replay[0]['id'], $replay[1]['id']);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_allocations', 1);
        $this->assertDatabaseCount('booking_events', 1);

        // Two different plots and actors compete for the remaining 150.01 credit.
        $drafts = [];
        foreach (['BOOK-2', 'BOOK-3'] as $number) {
            $plot = app(Catalogue::class)->plot($f['admin'], ['block_id' => $f['plot']->block_id, 'plot_size_id' => $f['plot']->plot_size_id, 'number' => $number]);
            $draft = app(BookingDrafts::class)->save($f['agent'], ['customer_id' => $f['customer']->id, 'plot_id' => $plot->id, 'quoted_price' => '1000.01', 'creation_key' => (string) Str::uuid()]);
            app(DraftPurchasers::class)->save($f['agent'], $draft->id, ['version' => 0, 'purchasers' => [['customer_id' => $f['customer']->id, 'share' => '100']]]);
            app(DraftTerms::class)->save($f['agent'], $draft->id, [...DraftTerm::firstOrFail()->snapshot['input'], 'version' => 0, 'source_version' => 1, 'policy_versions' => ['booking' => 1, 'installments' => 1]]);
            $drafts[] = $draft;
        }
        $denied = $this->race([[$f['agent']->id, $drafts[0]->id, $f['receipt']->id, (string) Str::uuid()], [$f['admin']->id, $drafts[1]->id, $f['receipt']->id, (string) Str::uuid()]]);
        $states = array_column($denied, 'status');
        sort($states);
        $this->assertSame(['rejected', 'saved'], $states);
        $this->assertSame('200.02', DB::table('booking_allocations')->sum('amount'));
        $this->assertDatabaseCount('account_movements', 1);
        $this->assertDatabaseCount('bookings', 2);

        // The same plot cannot be confirmed through another draft/key.
        DB::table('booking_drafts')->where('id', $drafts[0]->id)->update(['plot_id' => $f['plot']->id]);
        $blocked = $this->race([[$f['agent']->id, $drafts[0]->id, $f['receipt']->id, (string) Str::uuid()], [$f['admin']->id, $drafts[0]->id, $f['receipt']->id, (string) Str::uuid()]]);
        $this->assertSame(['conflict', 'conflict'], array_column($blocked, 'status'));
        $this->assertDatabaseCount('bookings', 2);
    }

    /** @param list<array{int,int,int,string}> $jobs
     * @return list<array<string,mixed>>
     */
    private function race(array $jobs): array
    {
        $directory = storage_path('framework/testing/booking-race-'.Str::uuid());
        mkdir($directory, 0777, true);
        $processes = [];
        try {
            foreach ($jobs as $index => [$actor, $draft, $receipt, $key]) {
                $side = $index === 0 ? 'left' : 'right';
                $process = new Process([PHP_BINARY, base_path('tests/Support/final-booking-race-worker.php'), $directory, $side, (string) $actor, (string) $draft, (string) $receipt, $key], base_path());
                $process->setTimeout(90)->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 45;
            while (! is_file($directory.'/left.ready') || ! is_file($directory.'/right.ready')) {
                foreach ($processes as $index => $process) {
                    $side = $index === 0 ? 'left' : 'right';
                    if (! $process->isRunning() && ! is_file($directory.'/'.$side.'.ready')) {
                        $this->fail('Booking worker exited before database barrier.');
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Booking writers failed to reach barrier.');
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
