<?php

use App\Domain\Inventory\Holds;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDatabaseName() !== 'estateflow_test' || ! str_starts_with(DB::selectOne('SELECT VERSION() AS version')->version, '8.4.')) {
    throw new RuntimeException('Hold race requires isolated MySQL 8.4 testing.');
}
[$script, $directory, $side, $actorId, $plotId, $operation, $key] = $argv;
file_put_contents($directory.'/'.$side.'.ready', 'ready');
$deadline = microtime(true) + 30;
while (! is_file($directory.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Hold race barrier timed out.');
    }
    usleep(10000);
}
try {
    $hold = app(Holds::class)->execute(User::findOrFail($actorId), (int) $plotId, $operation, $key, 'DEMO race purpose');
    echo json_encode(['status' => 'created', 'id' => $hold->id]);
} catch (ConflictHttpException) {
    echo json_encode(['status' => 'conflict']);
} catch (Throwable $exception) {
    echo json_encode(['status' => 'error', 'class' => $exception::class]);
    exit(1);
}
