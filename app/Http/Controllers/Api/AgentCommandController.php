<?php

namespace App\Http\Controllers\Api;

use App\Models\AgentCommand;
use App\Models\RecoveryAction;
use App\Services\Agent\RemoteHealthIngestService;
use App\Services\Network\IncidentManager;
use App\Services\Network\RecoveryPlan;
use Illuminate\Http\Request;

class AgentCommandController extends AgentApiController
{
    public function index(Request $request)
    {
        $device = $this->authenticatedDevice($request);
        if (!$device) return $this->unauthenticated();

        AgentCommand::query()
            ->where('device_id', $device->id)
            ->whereIn('status', ['queued', 'dispatched'])
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired', 'completed_at' => now()]);

        $commands = AgentCommand::query()
            ->where('device_id', $device->id)
            ->where('status', 'queued')
            ->orderBy('queued_at')
            ->limit(5)
            ->get();

        foreach ($commands as $command) {
            $command->update(['status' => 'dispatched', 'dispatched_at' => now()]);
        }

        return response()->json([
            'commands' => $commands->map(fn ($command) => [
                'id' => $command->id,
                'command_code' => $command->command_code,
                'payload' => $command->payload ?? new \stdClass(),
                'expires_at' => $command->expires_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    public function result(Request $request, AgentCommand $command, RemoteHealthIngestService $ingest, IncidentManager $incidentManager, RecoveryPlan $plan)
    {
        $device = $this->authenticatedDevice($request);
        if (!$device) return $this->unauthenticated();
        if ($command->device_id !== $device->id) return response()->json(['message' => 'Command does not belong to this device.'], 403);

        $data = $request->validate([
            'successful' => ['required', 'boolean'],
            'output' => ['nullable', 'string', 'max:4000'],
            'error' => ['nullable', 'string', 'max:4000'],
            'metrics' => ['required', 'array'],
        ]);

        $metrics = $data['metrics'];
        $result = $ingest->ingest($device, $metrics, 'remote_recovery_after', false);
        $after = $result['check'];

        $recovery = $command->recoveryAction;
        $incident = $command->incident;

        $verified = (bool) $data['successful'] && $recovery && $incident
            ? ($recovery->beforeHealthCheck
                ? $plan->cleared($incident->code, $recovery->beforeHealthCheck, $after)
                : $after->health_score >= 90)
            : false;

        if ($recovery) {
            $recovery->update([
                'after_health_check_id' => $after->id,
                'completed_at' => now(),
                'success' => $verified,
                'output_excerpt' => $this->excerpt($data['output'] ?? null),
                'error_message' => $data['successful'] ? null : $this->excerpt($data['error'] ?? null),
            ]);
        }

        $command->update([
            'status' => $verified ? 'completed' : 'failed',
            'completed_at' => now(),
            'result_output' => $this->excerpt($data['output'] ?? null),
            'result_error' => $this->excerpt($data['error'] ?? null),
        ]);

        if ($incident) {
            $diagnosis = $result['diagnosis'];
            if ($verified) {
                $incidentManager->resolve($incident, $after);
            } elseif ($data['successful'] && $diagnosis->code !== $incident->code && $diagnosis->code !== 'configuration_unreadable') {
                $incidentManager->synchronize($after, $diagnosis);
            } else {
                [$nextAction, $nextRecommendation] = $plan->nextStep($incident, (string) $command->command_code, $after);
                $incident->update([
                    'last_detected_at' => $after->checked_at,
                    'latest_health_check_id' => $after->id,
                    'recovery_action_code' => $nextAction,
                    'recommended_action' => $nextRecommendation,
                ]);
            }
        }

        return response()->json([
            'ok' => true,
            'verified' => $verified,
            'health_score' => $after->health_score,
            'diagnosis_title' => $after->diagnosis_title,
        ]);
    }

    private function excerpt(?string $text): ?string
    {
        $text = trim((string) $text);
        if ($text === '') return null;
        return mb_substr(preg_replace('/\s+/', ' ', $text) ?: $text, 0, 700);
    }
}
