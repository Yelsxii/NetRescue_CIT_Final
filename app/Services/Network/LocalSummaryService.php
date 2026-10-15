<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Support\CheckStory;

class LocalSummaryService
{
    public function build(): array
    {
        $host = Device::localHost();
        $checks = HealthCheck::where('device_id', $host->id)
            ->where('checked_at', '>=', now()->subDay())->orderBy('checked_at')->orderBy('id')->get();
        $measured = $checks->filter(fn (HealthCheck $check) => $check->readingFinished());
        $latest = $host->healthChecks()->first();
        $incidents = Incident::where('device_id', $host->id)->where('status', 'active')
            ->with(['device', 'latestHealthCheck', 'recoveryActions.beforeHealthCheck', 'recoveryActions.afterHealthCheck'])
            ->orderBy('first_detected_at')->orderBy('id')->get();

        $state = match (true) {
            ! $latest => ['status' => 'unknown', 'title' => 'Waiting for network measurements',
                'message' => 'Run Check to measure the network connection on this computer.'],
            ! $latest->readingFinished() => ['status' => 'unknown', 'title' => 'Network reading did not finish',
                'message' => 'The latest attempt did not measure the connection. Review the last completed reading below.'],
            $latest->diagnosis_code === 'healthy' && $incidents->isEmpty() => ['status' => 'healthy',
                'title' => 'This computer is connected', 'message' => 'The configured connectivity checks passed.'],
            default => ['status' => 'degraded', 'title' => 'This computer needs attention',
                'message' => $latest->diagnosis_summary ?: 'Review the current condition and available repair below.'],
        };

        return [
            'host' => $host,
            'stats' => [
                'healthy_check_rate_24h' => $measured->isNotEmpty()
                    ? round($measured->where('health_status', 'healthy')->count() / $measured->count() * 100) : null,
                'checks_24h' => $measured->count(),
                'active_incidents' => $incidents->count(),
            ],
            'response_points' => CheckStory::points($checks),
            'active_incidents' => $incidents,
            'state' => $state,
        ];
    }
}
