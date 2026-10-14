<?php

namespace App\Http\Controllers;

use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\MonitorSetting;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $query = Incident::query()
            ->with(['latestHealthCheck', 'closingHealthCheck', 'device', 'recoveryActions.beforeHealthCheck', 'recoveryActions.afterHealthCheck'])
            ->orderByRaw("case when status = 'active' then 0 else 1 end")
            ->orderByRaw("case when status = 'active' then case severity when 'critical' then 0 when 'high' then 1 when 'moderate' then 2 when 'low' then 3 else 4 end else 4 end")
            ->latest('last_detected_at');

        if ($request->filled('status') && in_array($request->string('status')->toString(), ['active', 'resolved'], true)) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('severity') && in_array($request->string('severity')->toString(), ['critical', 'high', 'moderate', 'low'], true)) {
            $query->where('severity', $request->string('severity')->toString());
        }
        if ($request->filled('device')) {
            $query->where('device_id', (int) $request->input('device'));
        }

        $incidents = $query->paginate(12)->withQueryString();

        $typeCounts = Incident::query()
            ->where('first_detected_at', '>=', now()->subDays(30))
            ->selectRaw('code, title, count(*) as total')
            ->groupBy('code', 'title')
            ->orderByDesc('total')
            ->get();

        $healthSummary = HealthCheck::query()
            ->where('checked_at', '>=', now()->subDays(7))
            ->selectRaw('ROUND(AVG(health_score), 1) as average_score, MIN(health_score) as minimum_score, COUNT(*) as total_checks')
            ->first();

        $devices = \App\Models\Device::query()->orderBy('display_name')->get();

        return view('history.index', compact('incidents', 'typeCounts', 'healthSummary', 'devices'));
    }

    public function show(Request $request, Incident $incident)
    {
        $incident->load([
            'device',
            'latestHealthCheck',
            'openingHealthCheck',
            'closingHealthCheck',
            'recoveryActions.beforeHealthCheck',
            'recoveryActions.afterHealthCheck',
        ]);

        $openingCheck = $incident->openingEvidence();
        $closingCheck = $incident->closingEvidence();

        $warningMs = (int) MonitorSetting::current()->latency_warning_ms;
        $view = $request->header('X-Panel') === '1' ? 'incidents.panel' : 'incidents.show';

        return view($view, compact('incident', 'openingCheck', 'closingCheck', 'warningMs'));
    }
}
