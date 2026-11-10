<?php

namespace Tests\Feature;

use App\Jobs\RecordQueueHeartbeat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RedisQueueTest extends TestCase
{
    public function test_real_redis_worker_delivers_a_retry_safe_probe(): void
    {
        if (! filter_var(getenv('RUN_REDIS_TESTS'), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Real Redis integration requires RUN_REDIS_TESTS=true and an isolated Redis service.');
        }
        config(['cache.default' => 'redis']);
        $probe = 'ci-'.bin2hex(random_bytes(8));
        Redis::connection()->ping();
        try {
            RecordQueueHeartbeat::dispatch($probe)->onConnection('redis')->onQueue($probe);
            $this->artisan('queue:work', ['connection' => 'redis', '--queue' => $probe, '--once' => true, '--sleep' => 0])
                ->assertSuccessful();
            $this->assertSame('ok', Cache::get('queue-probe:'.$probe));
        } finally {
            Cache::forget('queue-probe:'.$probe);
        }
    }
}
