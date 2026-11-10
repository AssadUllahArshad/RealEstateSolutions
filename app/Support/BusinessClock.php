<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class BusinessClock
{
    public function today(): string
    {
        return CarbonImmutable::now(config('estateflow.business_timezone'))->toDateString();
    }
}
