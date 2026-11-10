<?php

use App\Domain\Finance\InstallmentLedger;
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
    throw new RuntimeException('Installment race requires isolated MySQL 8.4.');
}
[$script, $directory, $side, $actor, $booking, $receipt, $key] = $argv;
$connection = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
file_put_contents($directory.'/'.$side.'.ready', 'ready');
$deadline = microtime(true) + 60;
while (! is_file($directory.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Installment race barrier timed out.');
    }
    usleep(10000);
}
try {
    $batch = app(InstallmentLedger::class)->allocate(User::findOrFail($actor), (int) $booking, ['command_key' => $key, 'version' => 0, 'policy_version' => 1, 'receipt_id' => (int) $receipt, 'amount' => '50.01', 'mode' => 'oldest_first', 'rows' => [], 'charge_rows' => [], 'confirmed' => true]);
    echo json_encode(['status' => 'saved', 'id' => $batch, 'actor' => (int) $actor, 'key' => $key, 'connection' => $connection]);
} catch (ConflictHttpException) {
    echo json_encode(['status' => 'conflict', 'connection' => $connection]);
} catch (ValidationException) {
    echo json_encode(['status' => 'rejected', 'connection' => $connection]);
} catch (Throwable $exception) {
    echo json_encode(['status' => 'error', 'class' => $exception::class]);
    exit(1);
}
