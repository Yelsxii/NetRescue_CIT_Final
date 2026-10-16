<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecoveryAction extends Model
{
    protected $fillable = [
        'device_id', 'incident_id', 'action_code', 'action_label', 'before_health_check_id',
        'after_health_check_id', 'started_at', 'completed_at', 'success',
        'output_excerpt', 'error_message', 'execution_status', 'verification_status', 'command_exit_code',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'success' => 'boolean',
            'command_exit_code' => 'integer',
        ];
    }

    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function incident(): BelongsTo { return $this->belongsTo(Incident::class); }
    public function beforeHealthCheck(): BelongsTo { return $this->belongsTo(HealthCheck::class, 'before_health_check_id'); }
    public function afterHealthCheck(): BelongsTo { return $this->belongsTo(HealthCheck::class, 'after_health_check_id'); }

    public function earlierVerificationChecks(): \Illuminate\Database\Eloquent\Collection
    {
        $ids = collect((array) data_get($this->afterHealthCheck?->probe_meta, 'recovery_verification.previous_check_ids', []))
            ->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id)))
            ->map(fn ($id) => (int) $id)->filter(fn ($id) => $id > 0 && $id !== $this->after_health_check_id)->unique()->values();

        if ($ids->isEmpty()) return (new HealthCheck)->newCollection();

        // Only exact retained readings from this computer are evidence. Do not
        // search for nearby timestamps or import another computer's reading.
        return HealthCheck::where('device_id', $this->device_id)->whereIn('id', $ids)
            ->orderBy('checked_at')->orderBy('id')->get();
    }
    public function commandTimedOut(): bool
    {
        if ($this->execution_status === 'timed_out') return true;
        // Interpret existing timeout evidence such as NR-00027 without rewriting
        // its stored flags. This is not an explicit non-zero command exit.
        return $this->execution_status === 'failed' && $this->command_exit_code === null
            && preg_match('/\AThe process ".*" exceeded the timeout of [0-9.]+ seconds\./s', (string) $this->error_message) === 1;
    }
    public function outcomeSummary(): string
    {
        if ($this->execution_status === 'skipped') {
            return match ($this->verification_status) {
                'already_recovered' => 'No command was run for this request. Its pre-repair check already showed recovery.',
                'unread' => 'No repair was run because the pre-repair reading did not finish.',
                'adapter_changed' => 'No repair was run because Windows selected a different adapter.',
                default => 'No repair was run because the pre-repair check recorded a different condition.',
            };
        }
        if ($this->commandTimedOut()) {
            $result = 'The command exceeded its time limit; command completion was not confirmed.';
            if (! $this->completed_at) return $result.' Automatic verification has not finished.';
            return $result.' '.match ($this->verification_status) {
                'adapter_changed' => 'The follow-up measured a different adapter, so recovery of the target was not verified.',
                'observed_recovery' => 'The follow-up observed recovery, but the command was not verified as its cause.',
                'unread' => 'The follow-up reading did not finish; recovery remains unverified.',
                default => 'The follow-up did not verify recovery of the target condition.',
            };
        }
        if (! $this->completed_at) {
            return match ($this->execution_status) {
                'completed' => 'The command completed; follow-up verification has not finished. Recovery is not yet verified.',
                'failed' => 'The command did not complete successfully; no completed follow-up reading is recorded yet.',
                default => 'Repair requested; command completion and recovery are not recorded yet.',
            };
        }
        if ($this->beforeHealthCheck?->diagnosis_code === 'healthy') {
            return 'The connection was already healthy before this attempt. This attempt did not verify a recovery.';
        }
        if ($this->execution_status === 'failed') {
            if ($this->verification_status === 'unread') {
                return $this->action_label.' did not complete successfully, and the follow-up reading did not finish.';
            }
            return $this->action_label.' did not complete successfully. The network result was checked separately.';
        }
        if ($this->success) {
            $summary = $this->action_label.' completed; the follow-up check verified that the target condition cleared.';
            if ($this->afterHealthCheck && $this->afterHealthCheck->diagnosis_code !== 'healthy') {
                $summary .= ' Another condition remains: '.($this->afterHealthCheck->diagnosis_title ?: $this->afterHealthCheck->diagnosis_code).'. Review its next step.';
            }
            return $summary;
        }
        if ($this->execution_status === 'completed') {
            return match ($this->verification_status) {
                'unread' => 'The command completed, but the follow-up reading did not finish; recovery was not verified.',
                'changed_condition' => 'The command completed; the follow-up reported a different condition. Recovery was not verified.',
                default => 'The command completed, but the follow-up did not verify that the target condition cleared.',
            };
        }
        if (filled($this->error_message)) {
            return 'A command error was recorded; recovery was not verified.';
        }
        return 'Repair success was not verified. This record does not separately confirm command completion.';
    }
    public function executionLabel(): string
    {
        if ($this->commandTimedOut()) return 'Command timed out; completion unconfirmed';
        return match ($this->execution_status) {
            'skipped' => 'Not run',
            'completed' => 'Command completed',
            'failed' => 'Command did not complete successfully',
            'pending' => 'Awaiting command result',
            default => 'Not recorded separately',
        };
    }

    public function verificationLabel(): string
    {
        return match ($this->verification_status) {
            'already_recovered' => 'Pre-repair check already healthy; this request ran no command',
            'verified' => 'Recovery verified after the command',
            'not_verified' => 'Recovery not verified',
            'changed_condition' => 'A different condition was measured',
            'adapter_changed' => 'A different adapter was measured',
            'observed_recovery' => 'Recovery observed; repair not verified',
            'unread' => 'The network reading did not finish',
            'pending' => 'Awaiting a verification result',
            default => $this->success ? 'Recovery verified' : 'Recovery not verified',
        };
    }
}
