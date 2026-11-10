<?php

namespace Tests\Feature;

use App\Jobs\RecordQueueHeartbeat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_id_is_generated_without_trusting_client_input(): void
    {
        $response = $this->withHeader('X-Request-ID', 'untrusted-client-value')->get('/login');
        $response->assertOk()->assertHeader('X-Request-ID');
        $this->assertTrue(Str::isUuid($response->headers->get('X-Request-ID')));
    }

    public function test_workspace_uses_karachi_business_date_and_no_financial_totals(): void
    {
        CarbonImmutable::setTestNow('2026-10-30 20:00:00 UTC');
        try {
            $this->actingAs(User::factory()->create())->get('/dashboard')
                ->assertInertia(fn (Assert $page) => $page->component('workspace')
                    ->where('businessDate', '2026-10-31')->where('timezone', 'Asia/Karachi')
                    ->where('currency', 'PKR')->missing('collections')->missing('receivables'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_mysql_schema_is_innodb_and_strict(): void
    {
        $tables = DB::select('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()');
        foreach ($tables as $table) {
            $this->assertSame('InnoDB', $table->ENGINE);
        }
        $this->assertStringContainsString('STRICT_TRANS_TABLES', DB::selectOne('SELECT @@SESSION.sql_mode AS mode')->mode);
        $this->assertSame('+00:00', DB::selectOne('SELECT @@SESSION.time_zone AS timezone')->timezone);
    }

    public function test_queue_probe_can_be_replayed_without_business_effects(): void
    {
        $job = new RecordQueueHeartbeat('foundation');
        $job->handle();
        $job->handle();
        $this->assertSame('ok', Cache::get('queue-probe:foundation'));
        $this->assertDatabaseCount('audit_events', 0);
    }
}
