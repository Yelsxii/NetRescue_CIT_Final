<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentCommand extends Model
{
    protected $fillable = [
        'device_id', 'incident_id', 'recovery_action_id', 'command_code', 'payload',
        'status', 'queued_at', 'dispatched_at', 'completed_at', 'expires_at',
        'result_output', 'result_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'queued_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function incident(): BelongsTo { return $this->belongsTo(Incident::class); }
    public function recoveryAction(): BelongsTo { return $this->belongsTo(RecoveryAction::class); }
}
