<?php

namespace App\Services\Network;

class HealthScoreCalculator
{
    public function calculate(array $metrics, int $latencyWarningMs): array
    {
        $weights = config('netrescue.health_weights', [
            'gateway' => 30,
            'internet' => 30,
            'dns' => 20,
            'response' => 20,
        ]);

        $score = 0;

        if ($metrics['gateway_reachable'] ?? false) {
            $score += (int) ($weights['gateway'] ?? 30);
        }

        if ($metrics['internet_reachable'] ?? false) {
            $score += (int) ($weights['internet'] ?? 30);
        }

        if ($metrics['dns_operational'] ?? false) {
            $score += (int) ($weights['dns'] ?? 20);
        }

        $gatewayLatency = $metrics['gateway_latency_ms'] ?? null;
        $responseHealthy = ($metrics['gateway_reachable'] ?? false)
            && is_numeric($gatewayLatency)
            && (float) $gatewayLatency <= $latencyWarningMs;

        if ($responseHealthy) {
            $score += (int) ($weights['response'] ?? 20);
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'status' => $this->statusFor($score),
            'response_healthy' => $responseHealthy,
        ];
    }

    public function statusFor(int $score): string
    {
        return match (true) {
            $score >= 90 => 'healthy',
            $score >= 70 => 'degraded',
            default => 'critical',
        };
    }
}
