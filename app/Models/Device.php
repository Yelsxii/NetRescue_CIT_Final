<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Device extends Model
{
    public const LOCAL_UUID = '00000000-0000-0000-0000-000000000001';

    protected $fillable = [
        'device_uuid', 'display_name', 'hostname', 'connection_type', 'is_local', 'recognized',
        'operating_system', 'os_version', 'last_ip', 'adapter_name',
        'gateway_ip', 'dns_servers', 'status', 'health_score', 'last_seen_at',
        'last_health_check_at', 'registered_at',
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
        return $this->hasMany(HealthCheck::class)->latest('checked_at')->orderByDesc('id');
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
        return $this->hasOne(HealthCheck::class)->ofMany(['checked_at' => 'max', 'id' => 'max']);
    }

    public function activeIncident(): HasOne
    {
        return $this->hasOne(Incident::class)->where('status', 'active')->latestOfMany();
    }

    public function activeIncidents(): HasMany
    {
        return $this->hasMany(Incident::class)->where('status', 'active')->latest('last_detected_at');
    }

    public static function localHost(): self
    {
        $hostname = gethostname() ?: 'This computer';

        return static::query()->firstOrCreate(
            ['device_uuid' => self::LOCAL_UUID],
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
}
