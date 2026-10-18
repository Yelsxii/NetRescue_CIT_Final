<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\Incident;
use App\Models\MonitorSetting;
use App\Support\NetworkDiagnosis;
use App\Support\Ipv4Address;
use Throwable;

class NetworkHealthService
{
    public function __construct(
        private readonly NetworkProbeService $probe,
        private readonly HealthScoreCalculator $scoreCalculator,
        private readonly DiagnosticEngine $diagnosticEngine,
        private readonly IncidentManager $incidentManager,
    ) {
    }

    public function run(string $source = 'manual', bool $manageIncidents = true, ?string $interfaceName = null, ?float $configurationTimeout = null): array
    {
        $settings = MonitorSetting::current();
        $device = Device::localHost();

        $runStarted = microtime(true);
        $timings = [];
        try {
            $phaseStarted = microtime(true);
            $configuration = $configurationTimeout !== null
                ? $this->probe->collectConfiguration($interfaceName, $configurationTimeout)
                : (filled($interfaceName) ? $this->probe->collectConfiguration($interfaceName) : $this->probe->collectConfiguration());
            $timings['configuration'] = round((microtime(true) - $phaseStarted) * 1000, 2);
            $localIp = $configuration['local_ip'] ?? null;
            $ipAssignment = $this->classifyIp($localIp);

            $metrics = [
                'platform_supported' => (bool) ($configuration['supported'] ?? false),
                'requested_interface_name' => $interfaceName,
                'configuration_unreadable' => filled($configuration['error'] ?? null),
                'adapter_available' => (bool) ($configuration['adapter_available'] ?? false),
                'adapter_state' => $configuration['adapter_state'] ?? null,
                'address_source' => $configuration['address_source'] ?? 'unknown',
                'dns_source' => $configuration['dns_source'] ?? 'unknown',
                'configuration_reader' => $configuration['reader'] ?? null,
                'configuration_error_code' => $configuration['error_code'] ?? null,
                'windows_adapter_status' => $configuration['windows_adapter_status'] ?? null,
                'interface_name' => $configuration['interface_name'] ?? null,
                'local_ip' => $localIp,
                'ip_assignment' => $ipAssignment,
                'gateway_ip' => $configuration['gateway_ip'] ?? null,
                'dns_servers' => $configuration['dns_servers'] ?? [],
                'gateway_reachable' => false, 'gateway_latency_ms' => null,
                'internet_reachable' => false, 'internet_latency_ms' => null,
                'dns_operational' => false, 'dns_latency_ms' => null,
                'probe_errors' => array_values(array_filter([$configuration['error'] ?? null])),
                'probe_states' => ['gateway' => 'skipped', 'internet' => 'skipped', 'dns' => 'skipped'],
            ];

            if (! $metrics['configuration_unreadable'] && $metrics['platform_supported'] && $metrics['adapter_available'] && $ipAssignment === 'valid' && $metrics['gateway_ip']) {
                $phaseStarted = microtime(true);
                $gateway = $this->probe->probeGateway($metrics['gateway_ip']);
                $timings['gateway'] = round((microtime(true) - $phaseStarted) * 1000, 2);
                $metrics['probe_states']['gateway'] = ($gateway['measured'] ?? true) ? ($gateway['reachable'] ? 'passed' : 'failed') : 'unavailable';
                $metrics['gateway_reachable'] = $gateway['reachable'];
                $metrics['gateway_latency_ms'] = $gateway['latency_ms'];
                if ($gateway['error']) $metrics['probe_errors'][] = $gateway['error'];

                // ICMP can be blocked while TCP and DNS still work. Measure the
                // external path before diagnosing an outage or offering renewal.
                $phaseStarted = microtime(true);
                $internet = $this->probe->probeInternet($settings->internet_targets ?: config('netrescue.internet_targets', []));
                $timings['internet'] = round((microtime(true) - $phaseStarted) * 1000, 2);
                $metrics['probe_states']['internet'] = $internet['reachable'] ? 'passed' : 'failed';
                $metrics['internet_reachable'] = $internet['reachable'];
                $metrics['internet_latency_ms'] = $internet['latency_ms'];
                $metrics['internet_target'] = $internet['target'];
                if ($internet['error']) $metrics['probe_errors'][] = $internet['error'];

                if ($metrics['internet_reachable']) {
                    $phaseStarted = microtime(true);
                    $dns = $this->probe->probeDns($settings->dns_test_domain);
                    $timings['dns'] = round((microtime(true) - $phaseStarted) * 1000, 2);
                    $metrics['probe_states']['dns'] = ($dns['measured'] ?? true) ? ($dns['operational'] ? 'passed' : 'failed') : 'unavailable';
                    $metrics['dns_operational'] = $dns['operational'];
                    $metrics['dns_latency_ms'] = $dns['latency_ms'];
                    $metrics['dns_addresses'] = $dns['addresses'];
                    if ($dns['error']) $metrics['probe_errors'][] = $dns['error'];
                }
            }

            $metrics['timings_ms'] = $timings + ['total' => round((microtime(true) - $runStarted) * 1000, 2)];
            $diagnosis = $this->diagnosticEngine->diagnose($metrics, $settings->latency_warning_ms);
            $score = $this->scoreFor($metrics, $settings->latency_warning_ms);
            if ($diagnosis->code === 'gateway_ping_unanswered') $score['status'] = 'degraded';
            $check = $this->persistCheck($device, $metrics, $diagnosis, $score, $source, $settings->dns_test_domain);
            $this->syncDevice($device, $check);
            $incident = $this->recordIncident($manageIncidents, $check, $diagnosis);

            return compact('check', 'incident', 'diagnosis', 'metrics');
        } catch (Throwable $e) {
            report($e);

            $metrics = [
                'platform_supported' => PHP_OS_FAMILY === 'Windows',
                'requested_interface_name' => $interfaceName,
                'configuration_unreadable' => true,
                'adapter_available' => false,
                'interface_name' => null, 'local_ip' => null, 'gateway_ip' => null, 'dns_servers' => [],
                'gateway_reachable' => false, 'gateway_latency_ms' => null,
                'internet_reachable' => false, 'internet_latency_ms' => null,
                'dns_operational' => false, 'dns_latency_ms' => null, 'probe_errors' => [$e->getMessage()],
            ];

            $metrics['timings_ms'] = $timings + ['total' => round((microtime(true) - $runStarted) * 1000, 2)];
            $diagnosis = $this->diagnosticEngine->diagnose($metrics, $settings->latency_warning_ms);
            $score = $this->scoreFor($metrics, $settings->latency_warning_ms);
            $check = $this->persistCheck($device, $metrics, $diagnosis, $score, $source, $settings->dns_test_domain);
            $this->syncDevice($device, $check);
            $incident = null;

            return compact('check', 'incident', 'diagnosis', 'metrics');
        }
    }

    private function persistCheck(Device $device, array $metrics, NetworkDiagnosis $diagnosis, array $score, string $source, string $dnsTestDomain): HealthCheck
    {
        return HealthCheck::create([
            'device_id' => $device->id,
            'checked_at' => now(),
            'source' => $source,
            'platform_supported' => (bool) ($metrics['platform_supported'] ?? false),
            'interface_name' => $metrics['interface_name'] ?? null,
            'local_ip' => $metrics['local_ip'] ?? null,
            'gateway_ip' => $metrics['gateway_ip'] ?? null,
            'dns_servers' => $metrics['dns_servers'] ?? [],
            'gateway_reachable' => (bool) ($metrics['gateway_reachable'] ?? false),
            'gateway_latency_ms' => $metrics['gateway_latency_ms'] ?? null,
            'internet_reachable' => (bool) ($metrics['internet_reachable'] ?? false),
            'internet_latency_ms' => $metrics['internet_latency_ms'] ?? null,
            'dns_operational' => (bool) ($metrics['dns_operational'] ?? false),
            'dns_latency_ms' => $metrics['dns_latency_ms'] ?? null,
            'dns_test_domain' => $dnsTestDomain,
            'health_score' => $score['score'],
            'health_status' => $score['status'],
            'diagnosis_code' => $diagnosis->code,
            'diagnosis_title' => $diagnosis->title,
            'severity' => $diagnosis->severity,
            'diagnosis_summary' => $diagnosis->summary,
            'diagnosis_details' => $diagnosis->details,
            'probe_meta' => [
                'timings_ms' => $metrics['timings_ms'] ?? [],
                'requested_interface_name' => $metrics['requested_interface_name'] ?? null,
                'internet_target' => $metrics['internet_target'] ?? null,
                'dns_addresses' => $metrics['dns_addresses'] ?? [],
                'errors' => $metrics['probe_errors'] ?? [],
                'response_healthy' => $score['response_healthy'] ?? false,
                'probe_states' => $metrics['probe_states'] ?? ['gateway' => 'skipped', 'internet' => 'skipped', 'dns' => 'skipped'],
                'adapter_state' => $metrics['adapter_state'] ?? null,
                'address_source' => $metrics['address_source'] ?? 'unknown',
                'dns_source' => $metrics['dns_source'] ?? 'unknown',
                'configuration_reader' => $metrics['configuration_reader'] ?? null,
                'configuration_error_code' => $metrics['configuration_error_code'] ?? null,
                'windows_adapter_status' => $metrics['windows_adapter_status'] ?? null,
            ],
        ]);
    }

    private function scoreFor(array $metrics, int $latencyWarningMs): array
    {
        if (($metrics['configuration_unreadable'] ?? false) || in_array('unavailable', $metrics['probe_states'] ?? [], true)) {
            return ['score' => 0, 'status' => 'unread', 'response_healthy' => false];
        }

        return $this->scoreCalculator->calculate($metrics, $latencyWarningMs);
    }

    private function recordIncident(bool $manageIncidents, HealthCheck $check, NetworkDiagnosis $diagnosis): ?Incident
    {
        if (! $manageIncidents || ! $check->readingFinished()) {
            return null;
        }

        return $this->incidentManager->synchronize($check, $diagnosis);
    }

    private function syncDevice(Device $device, HealthCheck $check): void
    {
        $payload = [
            'display_name' => gethostname() ?: $device->display_name,
            'hostname' => gethostname() ?: $device->hostname,
            'last_seen_at' => $check->checked_at,
            'last_health_check_at' => $check->checked_at,
        ];

        if ($check->readingFinished()) {
            $payload['last_ip'] = $check->local_ip;
            $payload['adapter_name'] = $check->interface_name;
            $payload['gateway_ip'] = $check->gateway_ip;
            $payload['dns_servers'] = $check->dns_servers;
            $payload['status'] = $check->health_status;
            $payload['health_score'] = $check->health_score;
        }

        $device->update($payload);
    }

    private function classifyIp(?string $ip): string
    {
        if (! $ip || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return 'missing';
        if (str_starts_with($ip, '169.254.')) return 'apipa';
        return Ipv4Address::usable($ip) ? 'valid' : 'missing';
    }
}
