<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Incident extends Model
{
    protected $fillable = [
        'device_id', 'code', 'title', 'severity', 'status', 'first_detected_at', 'last_detected_at',
        'resolved_at', 'occurrence_count', 'latest_health_check_id', 'summary',
        'recommended_action', 'recovery_action_code', 'opening_health_check_id', 'closing_health_check_id', 'resolution_kind',
    ];

    protected function casts(): array
    {
        return [
            'first_detected_at' => 'datetime',
            'last_detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'occurrence_count' => 'integer',
        ];
    }

    public function device(): BelongsTo { return $this->belongsTo(Device::class); }
    public function latestHealthCheck(): BelongsTo { return $this->belongsTo(HealthCheck::class, 'latest_health_check_id'); }
    public function openingHealthCheck(): BelongsTo { return $this->belongsTo(HealthCheck::class, 'opening_health_check_id'); }
    public function closingHealthCheck(): BelongsTo { return $this->belongsTo(HealthCheck::class, 'closing_health_check_id'); }
    public function recoveryActions(): HasMany { return $this->hasMany(RecoveryAction::class)->latest('started_at')->orderByDesc('id'); }

    public function getReferenceAttribute(): string
    {
        return 'NR-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->status === 'active';
    }

    public function plainProblem(): string
    {
        return match ($this->code) {
            'device_offline' => 'Stopped reporting',
            'slow_network', 'high_latency' => 'Slow response',
            'internet_unreachable', 'wan_unreachable' => 'Internet path failed',
            'device_unreachable' => 'No response',
            'dns_failure' => 'DNS Resolution Failure',
            'gateway_unreachable' => 'Gateway not responding',
            'gateway_ping_unanswered' => 'Gateway ping unanswered; Internet available',
            'adapter_disabled' => 'Adapter turned off',
            'adapter_disconnected' => 'No cable or Wi-Fi link',
            'static_configuration' => 'Manual address is not reaching the router',
            default => $this->title ?: 'Network problem',
        };
    }

    public function plainDetail(): string
    {
        return match ($this->code) {
            'device_offline' => 'An older record reported that a computer stopped responding.',
            'slow_network', 'high_latency' => 'The router or this computer answered, but slower than the warning limit.',
            'internet_unreachable', 'wan_unreachable' => 'The local network responded, but the Internet path did not.',
            'device_unreachable' => 'This computer did not answer during the monitoring window.',
            'dns_failure' => 'The router and the Internet answered, but name lookup failed.',
            'gateway_unreachable' => 'Gateway ping and external TCP tests failed. The exact failed device or policy is not established.',
            'gateway_ping_unanswered' => 'Gateway ping did not answer, but external TCP connectivity and name lookup passed.',
            default => $this->summary ?: 'A monitoring check recorded a problem.',
        };
    }

    public function repairCommand(): ?string
    {
        $adapter = $this->relationLoaded('latestHealthCheck')
            ? $this->latestHealthCheck?->interface_name
            : $this->latestHealthCheck()->value('interface_name');

        return match ($this->recovery_action_code) {
            'flush_dns' => 'ipconfig /flushdns',
            'renew_dhcp' => $adapter
                ? ($this->code === 'dhcp_issue' ? 'ipconfig /release "'.$adapter.'", then ipconfig /renew "'.$adapter.'"' : 'ipconfig /renew "'.$adapter.'"')
                : 'Renew the measured adapter (name unavailable)',
            'enable_adapter' => $adapter ? 'Enable-NetAdapter -Name "'.$adapter.'" -Confirm:$false -ErrorAction Stop' : 'Enable the measured adapter (name unavailable)',
            'restart_adapter' => $adapter ? 'Restart-NetAdapter -Name "'.$adapter.'"' : 'Restart-NetAdapter',
            default => null,
        };
    }

    public function canRepair(): bool
    {
        return $this->is_active && filled($this->repairCommand()) && $this->repairTargetReady();
    }

    public function repairTargetReady(): bool
    {
        return $this->device?->device_uuid === Device::LOCAL_UUID;
    }

    public function nextStep(): ?string
    {
        if ($this->is_active && $this->latestRecovery()?->verification_status === 'adapter_changed') {
            return 'The last follow-up measured another adapter. Check this incident\'s target again before running another repair command.';
        }
        return match ($this->code) {
            'device_offline' => 'Check that it is on and connected.',
            'slow_network' => 'Check the cable, switch port, and local traffic.',
            'internet_unreachable' => 'Check the router uplink and the Internet connection.',
            'device_unreachable' => 'Check power, the cable, and the switch port.',
            default => filled($this->recommended_action) ? trim((string) $this->recommended_action) : null,
        };
    }

    public function story(): array
    {
        return \App\Support\IncidentStory::for($this);
    }

    public function resolutionLabel(): string
    {
        return $this->story()['resolution'];
    }

    public function recoveryLine(): string
    {
        return $this->story()['repair'];
    }

    public function closingEvidence(): ?HealthCheck
    {
        $check = $this->closingHealthCheck;
        if (! $check && $this->resolved_at) {
            // A legacy repair may retain the exact after-check ID even when the incident did not.
            $linked = $this->latestRecovery()?->afterHealthCheck;
            if ($linked?->checked_at && $linked->checked_at->equalTo($this->resolved_at)
                && filled($linked->device_id) && (string) $linked->device_id === (string) $this->device_id) {
                $check = $linked;
            }
            // Never substitute an unrelated reading merely because its timestamp is nearby.
            if (! $check) {
                $candidate = $this->latestHealthCheck;
                if ($candidate?->checked_at && $candidate->checked_at->equalTo($this->resolved_at)
                    && $candidate->diagnosis_code !== $this->code) {
                    $check = $candidate;
                }
            }
        }
        return $check && filled($check->device_id) && (string) $check->device_id === (string) $this->device_id ? $check : null;
    }

    public function openingEvidence(): ?HealthCheck
    {
        $check = $this->openingHealthCheck;
        if (! $check && $this->device_id && $this->first_detected_at) {
            $check = HealthCheck::query()->where('device_id', $this->device_id)
                ->where('diagnosis_code', $this->code)->where('checked_at', $this->first_detected_at)
                ->orderBy('id')->first();
        }
        return $check && $check->device_id === $this->device_id ? $check : null;
    }

    public function durationLabel(): ?string
    {
        if (! $this->first_detected_at) {
            return null;
        }

        $end = $this->resolved_at ?? now();
        $seconds = abs($end->getTimestamp() - $this->first_detected_at->getTimestamp());
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $days.'d '.$hours.'h';
        }

        if ($hours > 0) {
            return $hours.' hr '.$minutes.' min';
        }

        if ($minutes > 0) {
            return $minutes.' min';
        }

        return 'under 1 min';
    }

    public function latestRecovery(): ?RecoveryAction
    {
        $actions = $this->relationLoaded('recoveryActions')
            ? $this->recoveryActions
            : $this->recoveryActions()->get();

        return $actions->first();
    }
}
