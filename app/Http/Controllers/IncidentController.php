<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\Incident;
use App\Models\MonitorSetting;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $query = Incident::query()
            ->where('device_id', Device::localHost()->id)
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
        $incidents = $query->paginate(12)->appends($request->only(['status', 'severity']));
        return view('history.index', compact('incidents'));
    }

    public function show(Request $request, Incident $incident)
    {
        abort_unless((int) $incident->device_id === Device::localHost()->id, 404);
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
