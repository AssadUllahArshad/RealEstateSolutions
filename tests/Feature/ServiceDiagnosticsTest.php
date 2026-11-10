<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class ServiceDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_selected_runtime_and_actual_mysql_checks_pass_without_mutating_data(): void
    {
        Redis::shouldReceive('connection')->never();
        $this->artisan('estateflow:doctor', ['--skip-redis' => true])
            ->expectsOutputToContain('SKIPPED')->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('audit_events', 0);
    }

    public function test_redis_failure_is_actionable_without_exposing_connection_details(): void
    {
        Redis::shouldReceive('connection')->once()->andThrow(new RuntimeException('Sensitive connection diagnostic'));
        $this->artisan('estateflow:doctor')
            ->expectsOutputToContain('Could not reach Redis.')
            ->doesntExpectOutputToContain('Sensitive connection diagnostic')
            ->assertFailed();
    }
}
