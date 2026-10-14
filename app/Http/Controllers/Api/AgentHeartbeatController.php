<?php

namespace App\Http\Controllers\Api;

use App\Models\MonitorSetting;
use Illuminate\Http\Request;

class AgentHeartbeatController extends AgentApiController
{
    public function store(Request $request)
    {
        $device = $this->authenticatedDevice($request);
        if (!$device) return $this->unauthenticated();

        $data = $request->validate([
            'hostname' => ['nullable', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'operating_system' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['nullable', 'string', 'max:64'],
        ]);

        $device->update(array_filter([
            'hostname' => $data['hostname'] ?? null,
            'display_name' => $data['display_name'] ?? null,
            'operating_system' => $data['operating_system'] ?? null,
            'os_version' => $data['os_version'] ?? null,
            'agent_version' => $data['agent_version'] ?? null,
            'last_seen_at' => now(),
        ], fn ($v) => $v !== null));

        $settings = MonitorSetting::current();

        return response()->json([
            'ok' => true,
            'server_time' => now()->toIso8601String(),
            'probe_config' => [
                'dns_test_domain' => $settings->dns_test_domain,
                'internet_target' => ($settings->internet_targets ?: config('netrescue.internet_targets', ['1.1.1.1:443']))[0] ?? '1.1.1.1:443',
            ],
        ]);
    }
}
