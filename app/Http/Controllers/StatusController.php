<?php

namespace App\Http\Controllers;

use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\MonitorSetting;
use App\Models\Device;
use App\Services\MonitorSchedulerStatus;

class StatusController extends Controller
{
    public function __invoke(MonitorSchedulerStatus $scheduler)
    {
        $host = Device::localHost();
        $latest = HealthCheck::query()->where('device_id', $host->id)->latest('checked_at')->orderByDesc('id')->first();
        $settings = MonitorSetting::current();
        $activeIncident = Incident::query()->where('device_id', $host->id)->where('status', 'active')->latest('last_detected_at')->orderByDesc('id')->first();

        return response()->json([
            'latest_check_id' => $latest?->id,
            'checked_at' => $latest?->checked_at?->toIso8601String(),
            'health_score' => $latest?->health_score,
            'health_status' => $latest?->health_status,
            'diagnosis_title' => $latest?->diagnosis_title,
            'monitoring_enabled' => $settings->enabled,
            'monitoring' => $scheduler->status($settings),
            'active_incident_id' => $activeIncident?->id,
        ]);
    }
}
