<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HealthCheck extends Model
{
    protected $fillable = [
        'device_id', 'checked_at', 'source', 'platform_supported', 'interface_name', 'local_ip', 'gateway_ip',
        'dns_servers', 'gateway_reachable', 'gateway_latency_ms', 'internet_reachable',
        'internet_latency_ms', 'dns_operational', 'dns_latency_ms', 'dns_test_domain',
        'health_score', 'health_status', 'diagnosis_code', 'diagnosis_title', 'severity',
        'diagnosis_summary', 'diagnosis_details', 'probe_meta',
    ];

    protected function casts(): array
    {
        return [
            'checked_at' => 'datetime',
            'platform_supported' => 'boolean',
            'dns_servers' => 'array',
            'gateway_reachable' => 'boolean',
            'gateway_latency_ms' => 'float',
            'internet_reachable' => 'boolean',
            'internet_latency_ms' => 'float',
            'dns_operational' => 'boolean',
            'dns_latency_ms' => 'float',
            'health_score' => 'integer',
            'diagnosis_details' => 'array',
            'probe_meta' => 'array',
        ];
    }

    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function recoveryActionsBefore(): HasMany { return $this->hasMany(RecoveryAction::class, 'before_health_check_id'); }
    public function recoveryActionsAfter(): HasMany { return $this->hasMany(RecoveryAction::class, 'after_health_check_id'); }

    public function getIsHealthyAttribute(): bool
    {
        return $this->diagnosis_code === 'healthy';
    }

    public function readingFinished(): bool
    {
        return ! in_array($this->diagnosis_code, ['configuration_unreadable', 'probe_unavailable'], true);
    }
    public function probeWasRun(string $probe): bool
    {
        $states = $this->probe_meta['probe_states'] ?? null;
        if (is_array($states) && array_key_exists($probe, $states)) {
            return in_array($states[$probe], ['passed', 'failed'], true);
        }
        // Infer old readings from the original probe pipeline, not from false alone.
        if (! $this->readingFinished() || ! $this->platform_supported) return false;
        $early = ['adapter_disabled', 'adapter_disconnected', 'adapter_unavailable', 'dhcp_issue',
            'ip_configuration_missing', 'gateway_missing'];
        if (in_array($this->diagnosis_code, $early, true)) return false;
        if (! $this->local_ip || str_starts_with($this->local_ip, '169.254.') || ! $this->gateway_ip) return false;
        if ($probe === 'gateway') return true;
        if (! $this->gateway_reachable) return false;
        if ($probe === 'internet') return true;
        return $this->internet_reachable;
    }
}
