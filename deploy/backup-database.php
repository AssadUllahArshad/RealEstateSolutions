<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$target = $argv[1] ?? null;

if (! is_string($target) || $target === '') {
    fwrite(STDERR, "Usage: php deploy/backup-database.php <absolute-backup-directory>\n");
    exit(2);
}

if (! is_dir($target) && ! mkdir($target, 0700, true) && ! is_dir($target)) {
    fwrite(STDERR, "Could not create backup directory.\n");
    exit(2);
}

$connectionName = (string) config('database.default');
$database = config("database.connections.{$connectionName}");

if (! is_array($database) || ! in_array($database['driver'] ?? null, ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "Production backup supports MySQL/MariaDB connections only.\n");
    exit(2);
}

$dumpBinary = (string) config('database.mysqldump_binary', '/usr/bin/mysqldump');

if (! is_executable($dumpBinary)) {
    fwrite(STDERR, "mysqldump is unavailable at the configured path.\n");
    exit(2);
}

$escape = static fn (mixed $value): string => str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '', ''], (string) $value);
$credentials = tempnam(sys_get_temp_dir(), 'estateflow-db-');

if ($credentials === false) {
    fwrite(STDERR, "Could not create the temporary database credential file.\n");
    exit(2);
}

$stamp = now('Asia/Karachi')->format('Ymd-His');
$sql = rtrim($target, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR."estateflow-{$stamp}.sql";
$gzip = $sql.'.gz';

try {
    file_put_contents($credentials, sprintf(
        "[client]\nhost=\"%s\"\nport=\"%s\"\nuser=\"%s\"\npassword=\"%s\"\n",
        $escape($database['host'] ?? '127.0.0.1'),
        $escape($database['port'] ?? '3306'),
        $escape($database['username'] ?? ''),
        $escape($database['password'] ?? ''),
    ));
    chmod($credentials, 0600);

    $process = new Process([
        $dumpBinary,
        "--defaults-extra-file={$credentials}",
        '--single-transaction',
        '--quick',
        '--routines',
        '--events',
        '--triggers',
        '--hex-blob',
        "--result-file={$sql}",
        (string) ($database['database'] ?? ''),
    ]);
    $process->setTimeout(900);
    $process->mustRun();

    $input = fopen($sql, 'rb');
    $output = gzopen($gzip, 'wb9');

    if ($input === false || $output === false) {
        throw new RuntimeException('Could not open database backup streams.');
    }

    while (! feof($input)) {
        $chunk = fread($input, 1024 * 1024);

        if ($chunk === false || gzwrite($output, $chunk) === false) {
            throw new RuntimeException('Could not compress database backup.');
        }
    }

    fclose($input);
    gzclose($output);
    file_put_contents($gzip.'.sha256', hash_file('sha256', $gzip).'  '.basename($gzip).PHP_EOL);
    chmod($gzip, 0600);
    chmod($gzip.'.sha256', 0600);

    fwrite(STDOUT, $gzip.PHP_EOL);
} catch (Throwable $exception) {
    @unlink($gzip);
    @unlink($gzip.'.sha256');
    fwrite(STDERR, "Database backup failed: {$exception->getMessage()}\n");
    exit(1);
} finally {
    @unlink($credentials);
    @unlink($sql);
}
