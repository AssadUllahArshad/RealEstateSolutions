<?php

use App\Domain\Finance\ReceiptDrafts;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDatabaseName() !== 'estateflow_test' || ! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
    throw new RuntimeException('Receipt race requires the isolated MySQL 8.4 test database.');
}
[$script, $directory, $side, $actorId, $customerId, $accountId, $key, $amount] = $argv;
$connection = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
file_put_contents($directory.'/'.$side.'.ready', 'ready');
$deadline = microtime(true) + 60;
while (! is_file($directory.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Receipt race barrier timed out.');
    }
    usleep(10000);
}
try {
    $receipt = app(ReceiptDrafts::class)->save(User::findOrFail($actorId), ['customer_id' => $customerId, 'financial_account_id' => $accountId, 'amount' => $amount, 'reported_on' => '2026-11-04', 'method' => 'cash', 'policy_version' => 1, 'creation_key' => $key]);
    echo json_encode(['status' => 'saved', 'id' => $receipt->id, 'connection' => $connection]);
} catch (ConflictHttpException) {
    echo json_encode(['status' => 'conflict', 'connection' => $connection]);
} catch (Throwable $exception) {
    echo json_encode(['status' => 'error', 'class' => $exception::class]);
    exit(1);
}
