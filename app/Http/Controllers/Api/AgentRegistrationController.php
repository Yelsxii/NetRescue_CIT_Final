<?php

namespace App\Http\Controllers\Api;

use App\Models\Device;
use App\Models\EnrollmentCode;
use App\Models\MonitorSetting;
use App\Services\Agent\LanPresenceService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentRegistrationController extends AgentApiController
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'enrollment_code' => ['required', 'string', 'max:32'],
            'device_uuid' => ['required', 'uuid'],
            'hostname' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'operating_system' => ['nullable', 'string', 'max:255'],
            'os_version' => ['nullable', 'string', 'max:255'],
            'agent_version' => ['required', 'string', 'max:64'],
            'gateway_ip' => ['nullable', 'string', 'max:45'],
        ]);

        $lan = app(LanPresenceService::class);
        $pairedByKey = $lan->keyMatches($data['enrollment_code']);

        if ($pairedByKey) {
            if (! $lan->sameLan((string) $request->server('REMOTE_ADDR', ''))) {
                return response()->json(['message' => 'This computer is not on the same router as NetRescue Central.'], 422);
            }

            if (($data['gateway_ip'] ?? '') !== $lan->snapshot()['gateway_ip']) {
                return response()->json(['message' => 'This computer is not using the same router as NetRescue Central.'], 422);
            }
        } else {
            $enrollment = EnrollmentCode::query()->where('code', $data['enrollment_code'])->first();
            if (!$enrollment || !$enrollment->usable) {
                return response()->json(['message' => 'This Wi-Fi key or deployment package is invalid, expired, or has reached its device limit.'], 422);
            }
        }

        $token = Str::random(64);

        $device = Device::query()->firstOrNew(['device_uuid' => $data['device_uuid']]);
        $isNewDevice = !$device->exists;

        $device->fill([
            'display_name' => $data['display_name'] ?: $data['hostname'],
            'hostname' => $data['hostname'],
            'connection_type' => 'agent',
            'is_local' => false,
            'recognized' => true,
            'operating_system' => $data['operating_system'] ?? 'Windows',
            'os_version' => $data['os_version'] ?? null,
            'agent_version' => $data['agent_version'],
            'status' => 'unknown',
            'auth_token_hash' => hash('sha256', $token),
            'registered_at' => $device->registered_at ?: now(),
            'last_seen_at' => now(),
        ]);
        $device->save();

        // Reinstalling the agent on the same endpoint UUID does not consume a
        // second deployment slot. Usage represents unique enrolled endpoints.
        if ($isNewDevice && isset($enrollment)) {
            $enrollment->increment('uses');
        }

        $settings = MonitorSetting::current();

        return response()->json([
            'message' => 'Device enrolled.',
            'device_id' => $device->id,
            'device_uuid' => $device->device_uuid,
            'token' => $token,
            'monitor_interval_seconds' => 60,
            'probe_config' => [
                'dns_test_domain' => $settings->dns_test_domain,
                'internet_target' => ($settings->internet_targets ?: config('netrescue.internet_targets', ['1.1.1.1:443']))[0] ?? '1.1.1.1:443',
            ],
        ], 201);
    }
}
