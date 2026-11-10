<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $app->make(Kernel::class)->bootstrap();
        $connection = config('database.default');
        $database = config('database.connections.mysql.database');
        if ($connection !== 'mysql' || $database !== 'estateflow_test' || ! $app->environment('testing')) {
            throw new RuntimeException('Tests require the isolated estateflow_test MySQL database.');
        }
        $version = DB::selectOne('SELECT VERSION() AS version')->version;
        if (! str_starts_with($version, '8.4.')) {
            throw new RuntimeException('Integration tests require actual MySQL 8.4, not MariaDB/SQLite.');
        }

        return $app;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
