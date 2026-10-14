<?php

namespace App\Http\Controllers;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\MonitorSetting;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $query = Device::query()
            ->with(['latestHealthCheck', 'activeIncidents'])
            ->where('recognized', true)
            ->orderByDesc('is_local')
            ->orderBy('display_name');

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if (in_array($status, ['healthy', 'degraded', 'critical', 'offline', 'unknown'], true)) {
                $query->where('status', $status);
            }
        }

        if ($request->filled('q')) {
            $term = trim((string) $request->string('q'));
            $query->where(function ($inner) use ($term) {
                $inner->where('display_name', 'like', "%{$term}%")
                    ->orWhere('hostname', 'like', "%{$term}%")
                    ->orWhere('last_ip', 'like', "%{$term}%");
            });
        }

        $devices = $query->get();
        $discoveredIn24h = Device::query()->where('registered_at', '>=', now()->subDay())->count();
        $checks24h = HealthCheck::query()->where('checked_at', '>=', now()->subDay())->count();
        $incidentCount = Incident::query()->where('status', 'active')->count();

        $stats = [
            'total' => $devices->count(),
            'online' => $devices->filter(fn (Device $device) => $device->is_online && ! in_array($device->status, ['offline', 'unknown'], true))->count(),
            'healthy' => $devices->where('status', 'healthy')->count(),
            'attention' => $devices->whereIn('status', ['degraded', 'critical'])->count(),
            'offline' => $devices->where('status', 'offline')->count(),
            'new' => $discoveredIn24h,
            'checks_24h' => $checks24h,
            'active_incidents' => $incidentCount,
        ];

        $unrecognized = Device::query()->where('recognized', false)->orderBy('last_ip')->get();

        return view('devices.index', compact('devices', 'stats', 'unrecognized'));
    }

    public function show(Device $device)
    {
        abort_unless($device->recognized, 404);

        $device->load(['activeIncidents.recoveryActions']);
        $latest = $device->healthChecks()->first();
        $measured = $latest && $latest->readingFinished()
            ? $latest
            : $device->healthChecks()->where('diagnosis_code', '!=', 'configuration_unreadable')->first();
        $activeIncidents = $device->activeIncidents;
        $settings = MonitorSetting::current();
        $trend = HealthCheck::query()
            ->where('device_id', $device->id)
            ->where('checked_at', '>=', now()->subDay())
            ->orderBy('checked_at')
            ->get(['id', 'checked_at', 'health_score', 'diagnosis_title', 'source', 'gateway_latency_ms', 'internet_latency_ms', 'dns_latency_ms', 'gateway_reachable', 'internet_reachable', 'dns_operational']);
        $checks24h = HealthCheck::query()
            ->where('device_id', $device->id)
            ->where('checked_at', '>=', now()->subDay())
            ->get();

        $measuredChecks24h = $checks24h->filter(fn (HealthCheck $check) => $check->readingFinished());
        $checkSuccessRate = $measuredChecks24h->isNotEmpty()
            ? round(($measuredChecks24h->where('health_status', 'healthy')->count() / $measuredChecks24h->count()) * 100)
            : null;

        $warningMs = (int) $settings->latency_warning_ms;

        return view('devices.show', compact('device', 'latest', 'measured', 'activeIncidents', 'trend', 'checks24h', 'checkSuccessRate', 'warningMs'));
    }

    public function checks(Device $device)
    {
        abort_unless($device->recognized, 404);

        $checks = $device->healthChecks()->limit(80)->get();
        $warningMs = (int) MonitorSetting::current()->latency_warning_ms;

        return view('devices.checks', compact('device', 'checks', 'warningMs'));
    }

    public function recognize(Device $device)
    {
        abort_if($device->is_local, 404);

        $device->update([
            'recognized' => true,
            'registered_at' => $device->registered_at ?: now(),
        ]);

        return back()->with('toast', [
            'type' => 'success',
            'title' => 'Computer recognized',
            'message' => $device->display_name.' is now in the monitored list.',
        ]);
    }
}
