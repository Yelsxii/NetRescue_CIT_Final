<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Device extends Model
{
    protected $fillable = [
        'device_uuid', 'display_name', 'hostname', 'connection_type', 'is_local', 'recognized',
        'operating_system', 'os_version', 'agent_version', 'last_ip', 'adapter_name',
        'gateway_ip', 'dns_servers', 'status', 'health_score', 'last_seen_at',
        'last_health_check_at', 'auth_token_hash', 'registered_at',
    ];

    protected function casts(): array
    {
        return [
            'is_local' => 'boolean',
            'recognized' => 'boolean',
            'dns_servers' => 'array',
            'health_score' => 'integer',
            'last_seen_at' => 'datetime',
            'last_health_check_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    public function healthChecks(): HasMany
    {
        return $this->hasMany(HealthCheck::class)->latest('checked_at');
    }

    public function incidents(): HasMany
    {
        return $this->hasMany(Incident::class)->latest('last_detected_at');
    }

    public function recoveryActions(): HasMany
    {
        return $this->hasMany(RecoveryAction::class)->latest('started_at');
    }

    public function latestHealthCheck(): HasOne
    {
        return $this->hasOne(HealthCheck::class)->latestOfMany('checked_at');
    }

    public function activeIncident(): HasOne
    {
        return $this->hasOne(Incident::class)->where('status', 'active')->latestOfMany();
    }

    public function activeIncidents(): HasMany
    {
        return $this->hasMany(Incident::class)->where('status', 'active')->latest('last_detected_at');
    }

    public function attentionLine(): string
    {
        $incidents = $this->relationLoaded('activeIncidents')
            ? $this->activeIncidents
            : $this->activeIncidents()->get();

        if ($incidents->isEmpty()) {
            return match ($this->status) {
                'healthy' => 'No open problem',
                'unknown' => 'Waiting for a measurement',
                'offline' => 'Not responding',
                default => 'Needs a look',
            };
        }

        $labels = $incidents->map(fn (Incident $incident) => $incident->plainProblem())->unique()->values();
        $specific = $labels->reject(fn (string $label) => $label === 'Stopped reporting')->values();

        if ($specific->isEmpty()) {
            return 'Stopped reporting';
        }

        $line = $specific->implode(' · ');

        if ($labels->contains('Stopped reporting')) {
            $line .= ' · stopped reporting';
        }

        return $line;
    }

    public function commands(): HasMany
    {
        return $this->hasMany(AgentCommand::class)->latest('queued_at');
    }

    public function getReferenceAttribute(): string
    {
        return $this->is_local ? 'CENTRAL-HOST' : 'DEV-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function getIsOnlineAttribute(): bool
    {
        if ($this->is_local) {
            return true;
        }

        return $this->last_seen_at?->gte(now()->subMinutes((int) config('netrescue.agent.offline_after_minutes', 3))) ?? false;
    }

    public static function localHost(): self
    {
        $hostname = gethostname() ?: 'NetRescue Central Host';

        return static::query()->firstOrCreate(
            ['device_uuid' => '00000000-0000-0000-0000-000000000001'],
            [
                'display_name' => $hostname,
                'hostname' => $hostname,
                'connection_type' => 'local',
                'is_local' => true,
                'recognized' => true,
                'operating_system' => PHP_OS_FAMILY,
                'status' => 'unknown',
                'registered_at' => now(),
            ]
        );
    }

    public static function generateUuid(): string
    {
        return (string) Str::uuid();
    }
}
