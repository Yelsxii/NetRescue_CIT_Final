<?php

namespace App\Http\Controllers;

use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\MonitorSetting;

class StatusController extends Controller
{
    public function __invoke()
    {
        $latest = HealthCheck::query()->latest('checked_at')->first();
        $settings = MonitorSetting::current();
        $activeIncident = Incident::query()->where('status', 'active')->latest('last_detected_at')->first();

        return response()->json([
            'latest_check_id' => $latest?->id,
            'checked_at' => $latest?->checked_at?->toIso8601String(),
            'health_score' => $latest?->health_score,
            'health_status' => $latest?->health_status,
            'diagnosis_title' => $latest?->diagnosis_title,
            'monitoring_enabled' => $settings->enabled,
            'active_incident_id' => $activeIncident?->id,
        ]);
    }
}
