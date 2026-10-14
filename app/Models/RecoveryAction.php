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
    public function outcomeSummary(): string
    {
        if ($this->execution_status === 'skipped') {
            return match ($this->verification_status) {
                'already_recovered' => 'No repair was run. The pre-repair check already showed recovery.',
                'unread' => 'No repair was run because the pre-repair reading did not finish.',
                'adapter_changed' => 'No repair was run because Windows selected a different adapter.',
                default => 'No repair was run because the pre-repair check recorded a different condition.',
            };
        }
        if (! $this->completed_at) {
            return 'Repair requested; execution and verification are pending.';
        }
        if ($this->beforeHealthCheck?->diagnosis_code === 'healthy') {
            return 'The connection was already healthy before this attempt. This attempt did not verify a recovery.';
        }
        if ($this->execution_status === 'failed') {
            return $this->action_label.' did not complete successfully. The network result was checked separately.';
        }
        if ($this->success) {
            return $this->action_label.' completed; the follow-up check verified that the target condition cleared.';
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
            'already_recovered' => 'Already recovered before any command was run',
            'verified' => 'Recovery verified after the command',
            'not_verified' => 'Recovery not verified',
            'changed_condition' => 'A different condition was measured',
            'adapter_changed' => 'A different adapter was measured',
            'unread' => 'The network reading did not finish',
            'pending' => 'Awaiting a verification result',
            default => $this->success ? 'Recovery verified' : 'Recovery not verified',
        };
    }
}
