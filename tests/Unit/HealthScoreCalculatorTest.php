<?php

namespace Tests\Unit;

use App\Services\Network\HealthScoreCalculator;
use Tests\TestCase;

class HealthScoreCalculatorTest extends TestCase
{
    public function test_healthy_metrics_score_one_hundred(): void
    {
        $result = app(HealthScoreCalculator::class)->calculate([
            'gateway_reachable' => true,
            'internet_reachable' => true,
            'dns_operational' => true,
            'gateway_latency_ms' => 12,
        ], 100);

        $this->assertSame(100, $result['score']);
        $this->assertSame('healthy', $result['status']);
    }

    public function test_dns_failure_with_good_response_scores_eighty(): void
    {
        $result = app(HealthScoreCalculator::class)->calculate([
            'gateway_reachable' => true,
            'internet_reachable' => true,
            'dns_operational' => false,
            'gateway_latency_ms' => 12,
        ], 100);

        $this->assertSame(80, $result['score']);
        $this->assertSame('degraded', $result['status']);
    }
}
