<?php

namespace App\Services\Network;

use App\Models\HealthCheck;
use App\Models\Incident;
use App\Support\NetworkDiagnosis;

class IncidentManager
{
    public function synchronize(HealthCheck $check, NetworkDiagnosis $diagnosis): ?Incident
    {
        $deviceId = $check->device_id;

        if ($diagnosis->code === 'healthy') {
            $this->resolveActiveExcept(null, $check, $deviceId);
            return null;
        }

        $this->resolveActiveExcept($diagnosis->code, $check, $deviceId);

        $incident = Incident::query()
            ->where('device_id', $deviceId)
            ->where('status', 'active')
            ->where('code', $diagnosis->code)
            ->first();

        if ($incident) {
            $hasAttempt = $incident->recoveryActions()->exists();
            $incident->update([
                'last_detected_at' => $check->checked_at,
                'occurrence_count' => $incident->occurrence_count + 1,
                'latest_health_check_id' => $check->id,
                'severity' => $diagnosis->severity,
                'summary' => $diagnosis->summary,
                'recommended_action' => $hasAttempt ? $incident->recommended_action : $diagnosis->recommendedAction,
                'recovery_action_code' => $hasAttempt ? $incident->recovery_action_code : $diagnosis->recoveryActionCode,
            ]);

            return $incident->fresh();
        }

        return Incident::create([
            'device_id' => $deviceId,
            'code' => $diagnosis->code,
            'title' => $diagnosis->title,
            'severity' => $diagnosis->severity,
            'status' => 'active',
            'first_detected_at' => $check->checked_at,
            'last_detected_at' => $check->checked_at,
            'occurrence_count' => 1,
            'latest_health_check_id' => $check->id,
            'opening_health_check_id' => $check->id,
            'summary' => $diagnosis->summary,
            'recommended_action' => $diagnosis->recommendedAction,
            'recovery_action_code' => $diagnosis->recoveryActionCode,
        ]);
    }

    public function resolve(Incident $incident, HealthCheck $check, string $kind = 'observed_recovery'): void
    {
        $incident->update([
            'status' => 'resolved',
            'resolved_at' => $check->checked_at,
            'closing_health_check_id' => $check->id,
            'resolution_kind' => $kind,
            'last_detected_at' => $check->checked_at,
            'latest_health_check_id' => $check->id,
        ]);
    }

    public function createOfflineIncident(\App\Models\Device $device): Incident
    {
        $latestCheckId = HealthCheck::query()
            ->where('device_id', $device->id)
            ->latest('checked_at')->orderByDesc('id')
            ->value('id');

        $incident = Incident::query()->firstOrCreate(
            ['device_id' => $device->id, 'status' => 'active', 'code' => 'device_offline'],
            [
                'title' => 'Endpoint Offline',
                'severity' => 'high',
                'first_detected_at' => now(),
                'last_detected_at' => now(),
                'occurrence_count' => 1,
                'latest_health_check_id' => $latestCheckId,
                'summary' => 'This recognized computer did not answer.',
                'recommended_action' => 'Check power and the network connection on that computer.',
            ]
        );

        if (! $incident->latest_health_check_id && $latestCheckId) {
            $incident->update(['latest_health_check_id' => $latestCheckId]);
        }

        return $incident->fresh();
    }

    private function resolveActiveExcept(?string $code, HealthCheck $check, ?int $deviceId): void
    {
        Incident::query()
            ->where('device_id', $deviceId)
            ->where('status', 'active')
            ->when($code, fn ($query) => $query->where('code', '!=', $code))
            ->update([
                'status' => 'resolved',
                'resolved_at' => $check->checked_at,
                'closing_health_check_id' => $check->id,
                'resolution_kind' => $code ? 'superseded' : 'observed_recovery',
                'last_detected_at' => $check->checked_at,
                'updated_at' => now(),
            ]);
    }
}
