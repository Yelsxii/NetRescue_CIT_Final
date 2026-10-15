<?php

namespace App\Services\Network;

use App\Models\Incident;
use App\Models\RecoveryAction;
use Throwable;

class RecoveryService
{
    public function __construct(
        private readonly NetworkHealthService $healthService,
        private readonly IncidentManager $incidentManager,
        private readonly RecoveryCommandRunner $commands,
        private readonly RecoveryPlan $plan,
    ) {}

    public function attempt(Incident $incident): RecoveryAction
    {
        if (! $incident->is_active) throw new \RuntimeException('This incident is no longer active.');
        if ($incident->device && ! $incident->device->is_local) throw new \RuntimeException('Remote recovery must be queued through its agent.');
        $actionCode = $incident->recovery_action_code;
        if (! in_array($actionCode, ['flush_dns', 'renew_dhcp', 'enable_adapter', 'restart_adapter'], true)) {
            throw new \RuntimeException('No local recovery action is defined for this incident.');
        }
        $targetName = $incident->latestHealthCheck?->interface_name;
        $beforeResult = $this->healthService->run('recovery_before', false);
        $before = $beforeResult['check'];
        $recovery = RecoveryAction::create([
            'device_id' => $incident->device_id, 'incident_id' => $incident->id,
            'action_code' => $actionCode, 'action_label' => $this->labelFor($actionCode, $incident->code),
            'before_health_check_id' => $before->id, 'started_at' => now(),
            'execution_status' => 'pending', 'verification_status' => 'pending',
        ]);

        // Never run a command against an old fault or a different measured adapter.
        if (! $before->readingFinished() || ! $before->platform_supported) {
            return $this->skip($recovery, 'unread');
        }
        if (filled($targetName) && $targetName !== $before->interface_name) {
            return $this->skip($recovery, 'adapter_changed');
        }
        if ($before->diagnosis_code === 'healthy') {
            $this->incidentManager->resolve($incident, $before, 'already_recovered');
            return $this->skip($recovery, 'already_recovered');
        }
        if ($before->diagnosis_code !== $incident->code) {
            $this->incidentManager->synchronize($before, $beforeResult['diagnosis']);
            return $this->skip($recovery, 'changed_condition');
        }

        $command = $this->commands->run($actionCode, $incident->code, $before->interface_name);
        // Persist execution before waiting for the next reading. A later timeout must
        // not erase the command result or turn an enabled adapter into a claimed failure.
        $recovery->update([
            'execution_status' => $command['successful'] ? 'completed' : 'failed',
            'verification_status' => 'pending', 'success' => null,
            'command_exit_code' => $command['exit_code'] ?? null,
            'output_excerpt' => $this->excerpt($command['output']),
            'error_message' => $command['successful'] ? null : $this->excerpt($command['error']),
        ]);
        if ($command['successful']) {
            usleep(in_array($actionCode, ['renew_dhcp', 'enable_adapter', 'restart_adapter'], true) ? 2500000 : 500000);
        }
        try {
            $afterResult = $this->healthService->run('recovery_after', false);
        } catch (Throwable $e) {
            report($e);
            $recovery->update([
                'completed_at' => now(), 'success' => false, 'verification_status' => 'unread',
                'error_message' => $this->excerpt(implode("\n", array_filter([
                    $recovery->error_message, 'Follow-up verification did not finish: '.$e->getMessage(),
                ]))),
            ]);
            $incident->update(['recommended_action' => 'Run Central Check to measure the connection. The command result is recorded, but recovery has not been verified.']);

            return $recovery->fresh(['beforeHealthCheck', 'afterHealthCheck', 'incident']);
        }
        $after = $afterResult['check'];
        $sameAdapter = blank($before->interface_name) || $before->interface_name === $after->interface_name;
        $conditionCleared = $after->readingFinished() && $after->platform_supported && $sameAdapter
            && $this->plan->cleared($incident->code, $before, $after);
        $verified = $command['successful'] && $conditionCleared;
        $verification = $verified ? 'verified' : (! $after->readingFinished() ? 'unread'
            : (! $sameAdapter ? 'adapter_changed' : ($after->diagnosis_code !== $incident->code ? 'changed_condition' : 'not_verified')));
        $recovery->update([
            'after_health_check_id' => $after->id, 'completed_at' => now(), 'success' => $verified,
            'execution_status' => $command['successful'] ? 'completed' : 'failed',
            'verification_status' => $verification, 'command_exit_code' => $command['exit_code'] ?? null,
            'output_excerpt' => $this->excerpt($command['output']),
            'error_message' => $command['successful'] ? null : $this->excerpt($command['error']),
        ]);

        if ($verified) {
            $this->incidentManager->resolve($incident, $after, 'verified_repair');
            $this->incidentManager->synchronize($after, $afterResult['diagnosis']);
        } elseif ($sameAdapter && $after->readingFinished() && $after->platform_supported && $conditionCleared) {
            $this->incidentManager->resolve($incident, $after, 'observed_recovery');
            $this->incidentManager->synchronize($after, $afterResult['diagnosis']);
        } elseif ($sameAdapter && $after->readingFinished() && $after->platform_supported && $after->diagnosis_code !== $incident->code) {
            $this->incidentManager->synchronize($after, $afterResult['diagnosis']);
        } elseif (! $after->readingFinished()) {
            $incident->update(['recommended_action' => 'Run Central Check to measure the connection. The command result is recorded, but recovery has not been verified.']);
        } elseif ($after->readingFinished() && $sameAdapter) {
            if ($command['successful']) {
                [$nextAction, $recommendation] = $this->plan->nextStep($incident, $actionCode, $after);
            } else {
                $nextAction = $actionCode;
                $recommendation = 'Windows did not complete the command sequence. Review the recorded error and permissions before retrying.';
            }
            $incident->update([
                'last_detected_at' => $after->checked_at, 'latest_health_check_id' => $after->id,
                'recovery_action_code' => $nextAction, 'recommended_action' => $recommendation,
            ]);
        }
        return $recovery->fresh(['beforeHealthCheck', 'afterHealthCheck', 'incident']);
    }

    private function skip(RecoveryAction $recovery, string $reason): RecoveryAction
    {
        $recovery->update(['execution_status' => 'skipped', 'verification_status' => $reason,
            'success' => null, 'completed_at' => now()]);
        return $recovery->fresh(['beforeHealthCheck', 'afterHealthCheck', 'incident']);
    }

    private function labelFor(string $code, string $incidentCode): string
    {
        return match ($code) {
            'flush_dns' => 'Clear the DNS cache',
            'renew_dhcp' => $incidentCode === 'dhcp_issue' ? 'Release and renew the address' : 'Renew the address',
            'enable_adapter' => 'Enable the adapter', 'restart_adapter' => 'Restart the adapter',
            default => 'Network recovery action',
        };
    }

    private function excerpt(?string $text): ?string
    {
        $text = trim((string) $text);
        return $text === '' ? null : mb_substr($text, 0, 4000);
    }
}
