<?php

use App\Domain\Finance\ReceiptPosting;
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
    throw new RuntimeException('Posting race requires isolated MySQL 8.4.');
}
[$script, $directory, $side, $actorId, $target, $operation, $key, $date] = $argv;
$connection = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
file_put_contents($directory.'/'.$side.'.ready', 'ready');
$deadline = microtime(true) + 60;
while (! is_file($directory.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Posting race barrier timed out.');
    }
    usleep(10000);
}
try {
    $input = ['command_key' => $key, 'expected_version' => 1, 'posting_version' => 1, 'business_date' => $date, 'note' => 'DEMO verified exact payment evidence for race test', 'confirmed' => true];
    $posting = app(ReceiptPosting::class);
    $actor = User::findOrFail($actorId);
    $receipt = str_starts_with($operation, 'accept') ? $posting->accept($actor, (int) $target, [...$input, 'stage' => $operation === 'accept-cleared' ? 'cleared' : 'confirmed', 'collection_version' => 1]) : $posting->advance($actor, (int) $target, 'clear', $input);
    echo json_encode(['status' => 'saved', 'id' => $receipt->id, 'number' => $receipt->number, 'connection' => $connection]);
} catch (ConflictHttpException) {
    echo json_encode(['status' => 'conflict', 'connection' => $connection]);
} catch (ValidationException $exception) {
    echo json_encode(['status' => 'rejected', 'fields' => array_keys($exception->errors()), 'connection' => $connection]);
} catch (Throwable $exception) {
    echo json_encode(['status' => 'error', 'class' => $exception::class]);
    exit(1);
}
