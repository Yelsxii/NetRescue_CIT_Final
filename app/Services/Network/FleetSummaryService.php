<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Support\CheckStory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FleetSummaryService
{
    public function build(): array
    {
        $devices = Device::query()
            ->where('recognized', true)
            ->with(['latestHealthCheck', 'activeIncident'])
            ->orderByDesc('is_local')
            ->orderBy('display_name')
            ->get();

        $total = $devices->count();
        $healthy = $devices->where('status', 'healthy')->count();
        $attention = $devices->whereIn('status', ['degraded', 'critical'])->count();
        $offline = $devices->where('status', 'offline')->count();
        $unknown = $devices->where('status', 'unknown')->count();
        $online = $devices->filter(fn (Device $device) => $device->is_online && ! in_array($device->status, ['offline', 'unknown'], true))->count();

        $scoredDevices = $devices->filter(fn (Device $device) => $device->health_score !== null);
        $averageHealth = $scoredDevices->isNotEmpty()
            ? round((float) $scoredDevices->avg('health_score'))
            : null;

        $checks24h = HealthCheck::query()
            ->where('checked_at', '>=', now()->subDay())
            ->orderBy('checked_at')
            ->get();

        $measuredChecks24h = $checks24h->filter(fn (HealthCheck $check) => $check->readingFinished());
        $healthyChecks24h = $measuredChecks24h->where('health_status', 'healthy')->count();
        $healthyCheckRate = $measuredChecks24h->isNotEmpty()
            ? round(($healthyChecks24h / $measuredChecks24h->count()) * 100)
            : null;

        $latencies = [
            'gateway' => $this->averageLatency($checks24h, 'gateway_latency_ms'),
            'internet' => $this->averageLatency($checks24h, 'internet_latency_ms'),
            'dns' => $this->averageLatency($checks24h, 'dns_latency_ms'),
        ];

        $latestCheck = HealthCheck::query()->latest('checked_at')->first();
        $activeIncidents = Incident::query()
            ->with(['device', 'recoveryActions'])
            ->where('status', 'active')
            ->orderBy('first_detected_at')
            ->get();

        $recentIncidents = Incident::query()
            ->with('device')
            ->latest('last_detected_at')
            ->limit(6)
            ->get();

        $incidentTypes = Incident::query()
            ->where('first_detected_at', '>=', now()->subDays(30))
            ->selectRaw('code, title, count(*) as total')
            ->groupBy('code', 'title')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $priorityDevices = $devices
            ->filter(fn (Device $device) => in_array($device->status, ['offline', 'critical', 'degraded'], true))
            ->sortBy(fn (Device $device) => match ($device->status) {
                'offline' => 1,
                'critical' => 2,
                'degraded' => 3,
                default => 4,
            })
            ->take(6)
            ->values();

        $fleetState = $this->fleetState($total, $attention, $offline, $unknown, $activeIncidents->count());

        return [
            'devices' => $devices,
            'stats' => [
                'total' => $total,
                'online' => $online,
                'healthy' => $healthy,
                'attention' => $attention,
                'offline' => $offline,
                'unknown' => $unknown,
                'average_health' => $averageHealth,
                'healthy_check_rate_24h' => $healthyCheckRate,
                'active_incidents' => $activeIncidents->count(),
                'checks_24h' => $measuredChecks24h->count(),
            ],
            'latencies' => $latencies,
            'trend' => $this->hourlyTrend($checks24h),
            'response_points' => CheckStory::points($checks24h),
            'latest_check' => $latestCheck,
            'active_incidents' => $activeIncidents,
            'problem_groups' => $this->problemGroups($activeIncidents),
            'recent_incidents' => $recentIncidents,
            'incident_types' => $incidentTypes,
            'priority_devices' => $priorityDevices,
            'fleet_state' => $fleetState,
        ];
    }

    private function problemGroups(Collection $incidents): Collection
    {
        $rank = [
            'device_unreachable' => 1,
            'internet_unreachable' => 2,
            'slow_network' => 3,
            'device_offline' => 4,
        ];

        return $incidents
            ->groupBy('code')
            ->map(function (Collection $rows) {
                /** @var Incident $sample */
                $sample = $rows->sortBy('first_detected_at')->first();
                $recoveries = $rows->map(fn (Incident $incident) => $incident->recoveryLine())->unique()->values();
                $severity = $rows->contains(fn (Incident $incident) => $incident->severity === 'critical')
                    ? 'critical'
                    : ($rows->contains(fn (Incident $incident) => $incident->severity === 'high') ? 'high' : $sample->severity);

                $recovery = $recoveries->count() === 1 ? $recoveries->first() : 'Recovery is not the same for every computer in this group.';
                $repairWasRun = $rows->contains(fn (Incident $incident) => $incident->recoveryActions->isNotEmpty());

                return (object) [
                    'code' => $sample->code,
                    'problem' => $sample->plainProblem(),
                    'detail' => $sample->plainDetail(),
                    'recovery' => $recovery,
                    'recovery_known' => $repairWasRun,
                    'action' => $sample->nextStep(),
                    'devices' => $rows->map(fn (Incident $incident) => $incident->device)->filter()->unique('id')->values(),
                    'duration' => $sample->durationLabel(),
                    'severity' => $severity,
                    'incident' => $sample,
                ];
            })
            ->sortBy(fn (object $group) => $rank[$group->code] ?? 9)
            ->values();
    }

    private function averageLatency(Collection $checks, string $field): ?float
    {
        $values = $checks
            ->pluck($field)
            ->filter(fn ($value) => is_numeric($value))
            ->map(fn ($value) => (float) $value);

        return $values->isNotEmpty() ? round((float) $values->avg(), 1) : null;
    }

    private function hourlyTrend(Collection $checks): Collection
    {
        return $checks
            ->groupBy(fn (HealthCheck $check) => $check->checked_at->format('Y-m-d H'))
            ->map(function (Collection $rows) {
                $rows = $rows->filter(fn (HealthCheck $check) => $check->readingFinished());
                if ($rows->isEmpty()) {
                    return null;
                }

                /** @var HealthCheck $last */
                $last = $rows->last();

                $passed = $rows->where('health_status', 'healthy')->count();

                return (object) [
                    'checked_at' => Carbon::parse($last->checked_at)->startOfHour(),
                    'health_score' => (int) round(($passed / $rows->count()) * 100),
                    'diagnosis_title' => $passed.' of '.$rows->count().' checks passed',
                    'source' => 'fleet',
                ];
            })
            ->filter()
            ->values();
    }

    private function fleetState(int $total, int $attention, int $offline, int $unknown, int $activeIncidents): array
    {
        if ($total === 0 || $unknown === $total) {
            return [
                'status' => 'unknown',
                'title' => 'Waiting for network measurements',
                'message' => 'NetRescue needs at least one real monitoring cycle before it can summarize the network state. Run a Central check to begin collecting data.',
            ];
        }

        if ($offline > 0 || $attention > 0) {
            $problemCount = $offline + $attention;

            return [
                'status' => $offline > 0 ? 'critical' : 'degraded',
                'title' => $problemCount.' device'.($problemCount === 1 ? '' : 's').' need attention',
                'message' => $activeIncidents > 0
                    ? $activeIncidents.' open incident'.($activeIncidents === 1 ? ' is' : 's are').' listed below from stored checks.'
                    : 'A recognized computer is reporting a fault.',
            ];
        }

        return [
            'status' => 'healthy',
            'title' => 'CIT network operations are stable',
                'message' => 'No open incident.',
        ];
    }
}
