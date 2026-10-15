<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\MonitorSetting;
use App\Services\Network\FleetSummaryService;

class DashboardController extends Controller
{
    public function __invoke(FleetSummaryService $fleetSummary)
    {
        $settings = MonitorSetting::current();
        $summary = $fleetSummary->build();
        $localDevice = Device::localHost();
        $localLatest = HealthCheck::query()
            ->where('device_id', $localDevice->id)
            ->latest('checked_at')->orderByDesc('id')
            ->first();

        $readingIncomplete = $localLatest && ! $localLatest->readingFinished();
        $measuredCheck = $readingIncomplete
            ? HealthCheck::query()
                ->where('device_id', $localDevice->id)
                ->where('diagnosis_code', '!=', 'configuration_unreadable')
                ->latest('checked_at')->orderByDesc('id')
                ->first()
            : $localLatest;

        $lastAutomatic = HealthCheck::query()
            ->where('device_id', $localDevice->id)
            ->where('source', 'automatic')
            ->latest('checked_at')->orderByDesc('id')
            ->first();

        $nextCheckAt = $settings->enabled
            ? $settings->nextAutomaticAt($lastAutomatic?->checked_at)
            : null;

        $networkNow = [
            ['label' => 'Gateway', 'ok' => $measuredCheck?->probeWasRun('gateway') ? $measuredCheck->gateway_reachable : null, 'ms' => $measuredCheck?->gateway_latency_ms],
            ['label' => 'Internet', 'ok' => $measuredCheck?->probeWasRun('internet') ? $measuredCheck->internet_reachable : null, 'ms' => $measuredCheck?->internet_latency_ms],
            ['label' => 'Name lookup', 'ok' => $measuredCheck?->probeWasRun('dns') ? $measuredCheck->dns_operational : null, 'ms' => $measuredCheck?->dns_latency_ms],
        ];

        $measured = collect($networkNow)->pluck('ms')->filter(fn ($value) => is_numeric($value))->map(fn ($value) => (float) $value);
        $networkScale = max((float) $settings->latency_warning_ms, $measured->max() ?? 1, 1);

        $networkNow = collect($networkNow)->map(function (array $row) use ($networkScale, $settings) {
            $row['width'] = is_numeric($row['ms'])
                ? ($networkScale > 0 ? round(((float) $row['ms'] / $networkScale) * 100, 2) : 0)
                : ($row['ok'] === false ? 100 : 8);
            $row['slow'] = is_numeric($row['ms']) && (float) $row['ms'] > (float) $settings->latency_warning_ms;

            return $row;
        });

        $recentChecks = HealthCheck::query()
            ->where('device_id', $localDevice->id)
            ->latest('checked_at')->orderByDesc('id')
            ->limit(5)
            ->get();

        if (($summary['fleet_state']['status'] ?? null) === 'healthy') {
            $slowRows = $networkNow->filter(fn (array $row) => $row['slow'])->values();
            if ($slowRows->isNotEmpty() && $measuredCheck) {
                $inside = $networkNow->reject(fn (array $row) => $row['slow'] || $row['ok'] === false)->pluck('label');
                $summary['fleet_state']['message'] = $slowRows->map(function (array $row) {
                    return $row['label'].' answered in '.number_format((float) $row['ms'], 1).' ms';
                })->implode(', ').', above the '.$settings->latency_warning_ms.' ms warning.'
                    .($inside->isNotEmpty() ? ' '.$inside->implode(' and ').' stayed inside the warning.' : '');
            }
        }

        return view('dashboard.index', compact(
            'settings',
            'summary',
            'localDevice',
            'localLatest',
            'readingIncomplete',
            'measuredCheck',
            'nextCheckAt',
            'networkNow',
            'networkScale',
            'recentChecks'
        ));
    }
}

