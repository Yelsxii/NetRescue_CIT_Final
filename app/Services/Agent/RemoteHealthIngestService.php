<?php

namespace App\Services\Agent;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\MonitorSetting;
use App\Services\Network\DiagnosticEngine;
use App\Services\Network\HealthScoreCalculator;
use App\Services\Network\IncidentManager;

class RemoteHealthIngestService
{
    public function __construct(
        private readonly HealthScoreCalculator $scoreCalculator,
        private readonly DiagnosticEngine $diagnosticEngine,
        private readonly IncidentManager $incidentManager,
    ) {}

    public function ingest(Device $device, array $metrics, string $source = 'agent', bool $manageIncidents = true): array
    {
        $settings = MonitorSetting::current();

        $metrics = array_merge([
            'platform_supported' => true,
            'adapter_available' => false,
            'adapter_state' => null,
            'address_source' => 'unknown',
            'dns_source' => 'unknown',
            'interface_name' => null,
            'local_ip' => null,
            'ip_assignment' => 'missing',
            'gateway_ip' => null,
            'dns_servers' => [],
            'gateway_reachable' => false,
            'gateway_latency_ms' => null,
            'internet_reachable' => false,
            'internet_latency_ms' => null,
            'dns_operational' => false,
            'dns_latency_ms' => null,
            'probe_errors' => [],
            'dns_addresses' => [],
            'internet_target' => null,
        ], $metrics);

        $metrics['ip_assignment'] = $this->classifyIp($metrics['local_ip'] ?? null);

        $score = $this->scoreCalculator->calculate($metrics, $settings->latency_warning_ms);
        $diagnosis = $this->diagnosticEngine->diagnose($metrics, $settings->latency_warning_ms);

        $check = HealthCheck::create([
            'device_id' => $device->id,
            'checked_at' => now(),
            'source' => $source,
            'platform_supported' => (bool) $metrics['platform_supported'],
            'interface_name' => $metrics['interface_name'],
            'local_ip' => $metrics['local_ip'],
            'gateway_ip' => $metrics['gateway_ip'],
            'dns_servers' => array_values(array_unique((array) $metrics['dns_servers'])),
            'gateway_reachable' => (bool) $metrics['gateway_reachable'],
            'gateway_latency_ms' => $metrics['gateway_latency_ms'],
            'internet_reachable' => (bool) $metrics['internet_reachable'],
            'internet_latency_ms' => $metrics['internet_latency_ms'],
            'dns_operational' => (bool) $metrics['dns_operational'],
            'dns_latency_ms' => $metrics['dns_latency_ms'],
            'dns_test_domain' => $settings->dns_test_domain,
            'health_score' => $score['score'],
            'health_status' => $score['status'],
            'diagnosis_code' => $diagnosis->code,
            'diagnosis_title' => $diagnosis->title,
            'severity' => $diagnosis->severity,
            'diagnosis_summary' => $diagnosis->summary,
            'diagnosis_details' => $diagnosis->details,
            'probe_meta' => [
                'internet_target' => $metrics['internet_target'],
                'dns_addresses' => $metrics['dns_addresses'],
                'errors' => array_values((array) $metrics['probe_errors']),
                'response_healthy' => $score['response_healthy'],
                'adapter_state' => $metrics['adapter_state'] ?? null,
                'address_source' => $metrics['address_source'] ?? 'unknown',
                'dns_source' => $metrics['dns_source'] ?? 'unknown',
            ],
        ]);

        $device->update([
            'last_ip' => $check->local_ip,
            'adapter_name' => $check->interface_name,
            'gateway_ip' => $check->gateway_ip,
            'dns_servers' => $check->dns_servers,
            'status' => $check->health_status,
            'health_score' => $check->health_score,
            'last_seen_at' => now(),
            'last_health_check_at' => $check->checked_at,
        ]);

        $incident = $manageIncidents ? $this->incidentManager->synchronize($check, $diagnosis) : null;

        return compact('check', 'incident', 'diagnosis', 'score');
    }

    private function classifyIp(?string $ip): string
    {
        if (!$ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return 'missing';
        if (str_starts_with($ip, '169.254.')) return 'apipa';
        return 'valid';
    }
}
