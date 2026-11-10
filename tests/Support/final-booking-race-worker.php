<?php

use App\Domain\Sales\FinalBookings;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDatabaseName() !== 'estateflow_test' || ! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
    throw new RuntimeException('Booking race requires isolated MySQL 8.4.');
}
[$script, $directory, $side, $actorId, $draft, $receipt, $key] = $argv;
$connection = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
file_put_contents($directory.'/'.$side.'.ready', 'ready');
$deadline = microtime(true) + 60;
while (! is_file($directory.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Booking race barrier timed out.');
    }
    usleep(10000);
}
try {
    $booking = app(FinalBookings::class)->confirm(User::findOrFail($actorId), (int) $draft, ['command_key' => $key, 'source_version' => 1, 'purchasers_version' => 1, 'terms_version' => 1, 'confirmed' => true, 'allocations' => [['receipt_id' => (int) $receipt, 'amount' => '100.01']]]);
    echo json_encode(['status' => 'saved', 'id' => $booking->id, 'connection' => $connection]);
} catch (ConflictHttpException) {
    echo json_encode(['status' => 'conflict', 'connection' => $connection]);
} catch (ValidationException) {
    echo json_encode(['status' => 'rejected', 'connection' => $connection]);
} catch (Throwable $exception) {
    echo json_encode(['status' => 'error', 'class' => $exception::class]);
    exit(1);
}
