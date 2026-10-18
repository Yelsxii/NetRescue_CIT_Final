<?php

namespace App\Services\Network;

use App\Models\Incident;
use App\Models\Device;
use App\Models\RecoveryAction;
use App\Exceptions\RepairConfirmationRequired;
use Illuminate\Support\Facades\Cache;
use Throwable;

class RecoveryService
{
    public function __construct(
        private readonly NetworkHealthService $healthService,
        private readonly IncidentManager $incidentManager,
        private readonly RecoveryCommandRunner $commands,
        private readonly RecoveryPlan $plan,
        private readonly RecoveryWaitClock $clock,
    ) {
    }

    public function attempt(Incident $incident, ?array $confirmation = null): RecoveryAction
    {
        if ((int) $incident->device_id !== Device::localHost()->id) {
            throw new \RuntimeException('Only incidents from this computer can be repaired.');
        }
        $ttl = app(NetworkRequestTimeout::class)->recoverySeconds((string) $incident->recovery_action_code, $incident->code) + 30;
        $lock = Cache::lock('netrescue:repair:'.$incident->device_id, $ttl);
        if (! $lock->get()) {
            throw new \RuntimeException('A repair for this computer is already running. Wait for its automatic verification to finish. No second repair command was run.');
        }
        try {
            $incident->refresh();
            return $this->attemptLocked($incident, $confirmation);
        } finally {
            $lock->release();
        }
    }

    private function attemptLocked(Incident $incident, ?array $confirmation): RecoveryAction
    {
        if (! $incident->is_active) throw new \RuntimeException('This incident is no longer active.');
        if ((int) $incident->device_id !== Device::localHost()->id) throw new \RuntimeException('Only incidents from this computer can be repaired.');
        $actionCode = $incident->recovery_action_code;
        if (! in_array($actionCode, ['flush_dns', 'renew_dhcp', 'enable_adapter', 'restart_adapter'], true)) {
            throw new \RuntimeException('No local recovery action is defined for this incident.');
        }
        if ($confirmation !== null) {
            // Check consent after taking the repair lock, against fresh state.
            // A stale page must never silently approve a different next action.
            $previousId = $incident->recoveryActions()->value('id') ?? 0;
            $actionChanged = filled($confirmation['action'] ?? null) && $confirmation['action'] !== $actionCode;
            $stale = (int) ($confirmation['previous_id'] ?? 0) !== (int) $previousId;
            $unconfirmed = $previousId && (! ($confirmation['confirmed'] ?? false) || ($confirmation['action'] ?? null) !== $actionCode);
            if ($actionChanged || $stale || $unconfirmed) {
                throw new RepairConfirmationRequired('Review the latest result and confirm the current repair option before trying again.');
            }
        }
        $targetName = $incident->latestHealthCheck?->interface_name;
        $beforeResult = $this->healthService->run('recovery_before', false, $targetName);
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

        $commandStarted = microtime(true);
        $command = $this->commands->run($actionCode, $incident->code, $before->interface_name);
        $commandMs = round((microtime(true) - $commandStarted) * 1000, 2);
        $execution = $command['successful'] ? 'completed' : (! empty($command['timed_out']) ? 'timed_out' : 'failed');
        // Persist execution before waiting for the next reading. A later timeout must
        // not erase the command result or turn an enabled adapter into a claimed failure.
        $recovery->update([
            'execution_status' => $execution,
            'verification_status' => 'pending', 'success' => null,
            'command_exit_code' => $command['exit_code'] ?? null,
            'output_excerpt' => $this->excerpt($command['output']),
            'error_message' => $command['successful'] ? null : $this->excerpt($command['error']),
        ]);
        try {
            $afterResult = $this->verifyAfter(
                $recovery,
                $actionCode,
                $before->interface_name,
                (bool) $command['successful'] || $execution === 'timed_out',
                $commandMs
            );
        } catch (Throwable $e) {
            report($e);
            $recovery->update([
                'completed_at' => now(), 'success' => false, 'verification_status' => 'unread',
                'error_message' => $this->excerpt(implode("\n", array_filter([
                    $recovery->error_message, 'Follow-up verification did not finish: '.$e->getMessage(),
                ]))),
            ]);
            $incident->update(['recommended_action' => 'Automatic verification could not obtain a completed reading. The command result is retained. Review the recorded reading error before retrying the check.']);

            return $recovery->fresh(['beforeHealthCheck', 'afterHealthCheck', 'incident']);
        }
        $after = $afterResult['check'];
        $sameAdapter = blank($before->interface_name) || $before->interface_name === $after->interface_name;
        $conditionCleared = $after->readingFinished() && $after->platform_supported && $sameAdapter
            && $this->plan->cleared($incident->code, $before, $after);
        $verified = $command['successful'] && $conditionCleared;
        $verification = $verified ? 'verified' : ($conditionCleared ? 'observed_recovery' : (! $after->readingFinished() ? 'unread'
            : (! $sameAdapter ? 'adapter_changed' : ($after->diagnosis_code !== $incident->code ? 'changed_condition' : 'not_verified'))));
        $recovery->update([
            'after_health_check_id' => $after->id, 'completed_at' => now(), 'success' => $verified,
            'execution_status' => $execution,
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
            $limit = data_get($after->probe_meta, 'recovery_verification.retry_policy') === 'reconnect_window'
                ? 'Automatic verification reached its reconnect limit.' : 'Automatic verification exhausted its reading attempts.';
            $incident->update(['recommended_action' => $limit.' The command result is retained, but recovery remains unverified. Review the reading error before retrying the check.']);
        } elseif (! $sameAdapter) {
            $incident->update(['recommended_action' => 'The follow-up measured a different adapter. Recovery of '.$before->interface_name.' remains unverified; review the recorded request before running another command.']);
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

    private function verifyAfter(RecoveryAction $recovery, string $actionCode, ?string $adapter, bool $mayBeReconnecting, float $commandMs): array
    {
        $attempts = max(1, min(3, (int) config('netrescue.verification.attempts', 3)));
        $delay = max(0, min(5, (float) config('netrescue.verification.retry_delay_seconds', 1)));
        $timeouts = app(NetworkRequestTimeout::class);
        $window = $timeouts->reconnectSeconds();
        $canUseWindow = $attempts > 1 && $window > 0 && $mayBeReconnecting && filled($adapter)
            && in_array($actionCode, ['enable_adapter', 'restart_adapter'], true);
        $poll = (float) config('netrescue.verification.reconnect_poll_seconds', 2);
        $poll = is_finite($poll) ? max(1, min(5, $poll)) : 2;
        $started = $this->clock->now();
        $windowActive = false;
        $lastResult = null;
        $previousIds = [];
        $errors = [];
        // Monotonic time bounds retries even when Carbon timestamps are frozen.
        // The count cap is a final backstop; transient reads do not consume a
        // pretend 20-second wait just because their process allowance was 20s.
        for ($attempt = 1; $attempt <= 60; $attempt++) {
            if ($attempt > 1) {
                $remaining = $window - ($this->clock->now() - $started);
                if ($windowActive && $remaining <= 0) {
                    return $this->endReconnectWindow($lastResult, $started);
                }
                $this->clock->wait($windowActive ? min($poll, $remaining) : $delay);
                if ($windowActive && $this->clock->now() - $started >= $window) {
                    return $this->endReconnectWindow($lastResult, $started);
                }
            }
            $configurationTimeout = (float) config('netrescue.timeouts.powershell_seconds', 20);
            if ($windowActive) {
                $configurationTimeout = min($configurationTimeout, $window - ($this->clock->now() - $started));
            }
            try {
                $result = $configurationTimeout < (float) config('netrescue.timeouts.powershell_seconds', 20)
                    ? $this->healthService->run('recovery_after', false, $adapter, $configurationTimeout)
                    : $this->healthService->run('recovery_after', false, $adapter);
            } catch (Throwable $e) {
                $errors[] = $e->getMessage();
                if ((! $windowActive && $attempt >= $attempts) || $attempt === 60
                    || ($windowActive && $this->clock->now() - $started >= $window)) {
                    throw $e;
                }
                continue;
            }
            $check = $result['check'];
            $sameAdapter = blank($adapter) || $adapter === $check->interface_name;
            $reconnecting = $mayBeReconnecting && in_array($actionCode, ['enable_adapter', 'restart_adapter', 'renew_dhcp'], true)
                && $sameAdapter && in_array(
                    $check->diagnosis_code,
                    ['adapter_disabled', 'adapter_disconnected', 'ip_configuration_missing', 'dhcp_issue', 'gateway_missing', 'gateway_unreachable'],
                    true
                );
            $transientUnread = ! $check->readingFinished() && ($sameAdapter || blank($check->interface_name))
                && in_array(
                    data_get($check->probe_meta, 'configuration_error_code'),
                    ['adapter_transitioning', 'adapter_snapshot_changed', 'process_timed_out'],
                    true
                );
            if ($canUseWindow && ($reconnecting || $transientUnread)) $windowActive = true;
            $elapsed = $this->clock->now() - $started;
            $stop = $check->readingFinished() && ! $reconnecting ? 'settled'
                : ($windowActive && $elapsed >= $window ? 'reconnect_window_elapsed'
                    : (! $windowActive && $attempt >= $attempts ? 'reading_limit' : ($attempt === 60 ? 'safety_cap' : null)));
            $meta = $check->probe_meta ?? [];
            $meta['recovery_verification'] = [
                'attempt' => $attempt, 'maximum_attempts' => $windowActive ? null : $attempts,
                'previous_check_ids' => $previousIds, 'reading_errors' => $errors, 'command_ms' => $commandMs,
                'command_timeout_seconds' => $timeouts->commandSeconds($actionCode), 'target_adapter' => $adapter,
                'configuration_timeout_seconds' => $configurationTimeout,
                'retry_policy' => $windowActive ? 'reconnect_window' : 'attempts',
                'reconnect_window_seconds' => $windowActive ? $window : null,
                'elapsed_ms' => round($elapsed * 1000, 2), 'stop_reason' => $stop,
            ];
            $check->update(['probe_meta' => $meta]);
            $recovery->update(['after_health_check_id' => $check->id]);
            $lastResult = $result;
            if ($stop !== null) return $result;
            $previousIds[] = $check->id;
        }
        throw new \RuntimeException('Automatic verification did not return a reading.');
    }

    private function endReconnectWindow(array $result, float $started): array
    {
        $meta = $result['check']->probe_meta;
        $meta['recovery_verification']['stop_reason'] = 'reconnect_window_elapsed';
        $meta['recovery_verification']['elapsed_ms'] = round(($this->clock->now() - $started) * 1000, 2);
        $result['check']->update(['probe_meta' => $meta]);
        return $result;
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
