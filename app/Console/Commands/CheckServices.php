<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

class CheckServices extends Command
{
    protected $signature = 'estateflow:doctor {--skip-redis : Explicitly omit the Redis connectivity check}';

    protected $description = 'Check runtime and service requirements without changing data or printing connection secrets';

    public function handle(): int
    {
        $failed = false;
        $rows = [];
        $check = function (string $name, bool $passes, string $detail) use (&$failed, &$rows): void {
            $failed = $failed || ! $passes;
            $rows[] = [$name, $passes ? 'PASS' : 'FAIL', $detail];
        };

        $check('PHP', version_compare(PHP_VERSION, '8.4.0', '>='), PHP_VERSION);
        foreach (['pdo_mysql', 'bcmath', 'mbstring', 'fileinfo', 'openssl', 'curl', 'zip'] as $extension) {
            $check('Extension '.$extension, extension_loaded($extension), 'Required by the foundation runtime');
        }
        $check('Application timezone', config('app.timezone') === 'UTC', 'Event timestamps use UTC');
        $check('Business timezone', config('estateflow.business_timezone') === 'Asia/Karachi', 'Business dates use Asia/Karachi');

        if (config('database.default') !== 'mysql') {
            $check('Database driver', false, 'MySQL is required; alternative drivers cannot certify integrity');
        } else {
            try {
                $settings = DB::selectOne('SELECT VERSION() AS version, @@SESSION.sql_mode AS sql_mode, @@SESSION.time_zone AS timezone, @@character_set_connection AS charset, @@SESSION.foreign_key_checks AS fk_checks');
                $check('MySQL version', str_starts_with($settings->version, '8.4.'), $settings->version);
                $check('Strict SQL mode', str_contains($settings->sql_mode, 'STRICT_TRANS_TABLES') || str_contains($settings->sql_mode, 'STRICT_ALL_TABLES'), 'Strict amount and value validation');
                $check('Database timezone', $settings->timezone === '+00:00', 'Session must explicitly use +00:00');
                $check('Database charset', $settings->charset === 'utf8mb4', 'Connection must use utf8mb4');
                $check('Foreign keys', (int) $settings->fk_checks === 1, 'Foreign key checks must remain enabled');
                $nonInnoDB = DB::selectOne("SELECT COUNT(*) AS count FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND ENGINE <> 'InnoDB'")->count;
                $check('Table engines', (int) $nonInnoDB === 0, 'All existing business tables must use InnoDB');
            } catch (Throwable) {
                $check('MySQL connection', false, 'Could not inspect MySQL. Check private environment settings and service availability.');
            }
        }

        if ($this->option('skip-redis')) {
            $rows[] = ['Redis connection', 'SKIPPED', 'Explicitly omitted; Redis integration is not certified'];
        } else {
            try {
                Redis::connection()->ping();
                $check('Redis connection', true, 'PING succeeded; run the isolated worker test to verify delivery');
            } catch (Throwable) {
                $check('Redis connection', false, 'Could not reach Redis. Check private environment settings and service availability.');
            }
        }

        $this->table(['Check', 'Result', 'Details'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
