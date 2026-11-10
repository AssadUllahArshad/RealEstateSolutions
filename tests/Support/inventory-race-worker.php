<?php

use App\Domain\Inventory\Catalogue;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->loadEnvironmentFrom('.env.testing');
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDatabaseName() !== 'estateflow_test') {
    throw new RuntimeException('Race worker requires the isolated test database.');
}
[$script, $directory, $side, $actorId, $blockId, $sizeId] = $argv;
file_put_contents($directory.'/'.$side.'.ready', 'ready');
$deadline = microtime(true) + 60;
while (! is_file($directory.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Race coordination timed out.');
    }
    usleep(10000);
}
try {
    app(Catalogue::class)->plot(User::findOrFail($actorId), [
        'number' => 'RACE-01', 'block_id' => $blockId, 'plot_size_id' => $sizeId,
    ]);
    echo json_encode(['status' => 'created']);
} catch (ValidationException) {
    echo json_encode(['status' => 'conflict']);
} catch (Throwable $exception) {
    echo json_encode(['status' => 'error', 'class' => $exception::class]);
    exit(1);
}
