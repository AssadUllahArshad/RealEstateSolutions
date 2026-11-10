<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RecordQueueHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public string $probeId) {}

    public function handle(): void
    {
        // A retry refreshes the same harmless cache key, never a business record.
        Cache::put('queue-probe:'.$this->probeId, 'ok', now()->addMinutes(10));
    }
}
