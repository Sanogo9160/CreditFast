<?php

namespace Tests\Feature;

use App\Services\InterestRateService;
use Tests\TestCase;

class InterestRateServiceTest extends TestCase
{
    public function test_institutional_rate_is_fixed_at_fifteen_percent(): void
    {
        $service = app(InterestRateService::class);

        $this->assertSame(15.0, $service->institutionalRate());
        $this->assertSame(15.0, $service->defaultRate());
        $this->assertSame(15.0, $service->proposeFromScore(95.0));
        $this->assertSame(15.0, $service->proposeFromScore(0.0));
        $this->assertSame(15.0, $service->clamp(18.0));
    }
}
