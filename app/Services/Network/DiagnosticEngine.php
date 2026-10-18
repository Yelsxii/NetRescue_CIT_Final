<?php

namespace App\Services\Network;

use App\Support\NetworkDiagnosis;

class DiagnosticEngine
{
    public function diagnose(array $metrics, int $latencyWarningMs): NetworkDiagnosis
    {
        if (!($metrics['platform_supported'] ?? true)) {
            return new NetworkDiagnosis(
                code: 'unsupported_platform',
                title: 'Unsupported Operating System',
                severity: 'critical',
                summary: 'NetRescue Auto can only perform Windows network probes on Windows 10 or Windows 11.',
                details: ['Run the utility on the Windows computer whose connection you want to monitor.'],
            );
        }

        if ($metrics['configuration_unreadable'] ?? false) {
            return new NetworkDiagnosis(
                code: 'configuration_unreadable',
                title: 'Network reading did not finish',
                severity: 'low',
                summary: in_array($metrics['configuration_error_code'] ?? null, ['adapter_transitioning', 'adapter_snapshot_changed'], true)
                    ? 'Windows has not returned a stable adapter state. This reading did not measure the connection.'
                    : 'Windows did not provide a complete adapter reading, so this check did not measure the network.',
                details: [
                    'This is not a missing adapter. The last completed reading is still the known configuration.',
                    'Gateway, Internet, and name lookup were not run for this check.',
                ],
                recommendedAction: 'Review the recorded Windows reading error. An incomplete reading does not prove the connection is down.',
            );
        }

        if (($metrics['adapter_state'] ?? null) === 'disabled') {
            $name = $metrics['interface_name'] ?? null ?: 'The network adapter';

            return new NetworkDiagnosis(
                code: 'adapter_disabled',
                title: 'Network Adapter Disabled',
                severity: 'critical',
                summary: $name.' is turned off in Windows.',
                details: [
                    'Windows still has the adapter, and it can be enabled.',
                    'Gateway, Internet, and name lookup were not run because the adapter is off.',
                ],
                recommendedAction: 'Try enabling adapter to ask Windows to turn on '.$name.'. NetRescue checks whether it reconnects; recovery is not guaranteed.',
                recoveryActionCode: filled($metrics['interface_name'] ?? null) ? 'enable_adapter' : null,
            );
        }

        if (($metrics['adapter_state'] ?? null) === 'disconnected') {
            $name = $metrics['interface_name'] ?? null ?: 'The network adapter';

            return new NetworkDiagnosis(
                code: 'adapter_disconnected',
                title: 'No Cable or Wi-Fi Link',
                severity: 'critical',
                summary: $name.' is enabled, but it has no connection.',
                details: [
                    'This is not a disabled adapter. Windows reports the link as disconnected.',
                    'A repair command cannot create a Wi-Fi signal or plug in a cable.',
                ],
                recommendedAction: 'Plug in the Ethernet cable, or turn Wi-Fi on and join the network.',
            );
        }

        if (!($metrics['adapter_available'] ?? false)) {
            return new NetworkDiagnosis(
                code: 'adapter_unavailable',
                title: 'No Active Network Adapter',
                severity: 'critical',
                summary: 'Windows did not report a Wi-Fi or Ethernet adapter.',
                details: [
                    'No enabled or disconnected Wi-Fi or Ethernet adapter was found.',
                    'External connectivity tests were not run because there is no adapter to measure.',
                ],
                recommendedAction: 'Check that the adapter is installed. A missing adapter cannot be turned on by a repair command.',
            );
        }

        if (($metrics['ip_assignment'] ?? 'missing') === 'apipa') {
            if ($this->usesManualAddress($metrics)) {
                return $this->manualAddress($metrics);
            }

            return new NetworkDiagnosis(
                code: 'dhcp_issue',
                title: 'Possible DHCP Configuration Issue',
                severity: 'high',
                summary: 'Windows has an automatic private address (169.254.x.x), which usually means a normal DHCP lease was not obtained.',
                details: [
                    'The adapter is active but does not have a normal LAN IPv4 assignment.',
                    'Gateway, Internet, and DNS checks are not treated as primary failures until the IP configuration is corrected.',
                ],
                recommendedAction: 'Try release and renew to request a fresh DHCP lease. NetRescue checks whether it helps; recovery is not guaranteed.',
                recoveryActionCode: 'renew_dhcp',
            );
        }

        if (($metrics['ip_assignment'] ?? 'missing') !== 'valid') {
            if ($this->usesManualAddress($metrics)) {
                return $this->manualAddress($metrics);
            }

            return new NetworkDiagnosis(
                code: 'ip_configuration_missing',
                title: 'IPv4 Configuration Missing',
                severity: 'high',
                summary: 'The active adapter does not have a usable IPv4 address.',
                details: ['Check DHCP or manual IPv4 configuration before testing external connectivity.'],
                recommendedAction: 'Try renewing address to request IPv4 configuration from the router, then check whether a usable address returns.',
                recoveryActionCode: 'renew_dhcp',
            );
        }

        if (blank($metrics['gateway_ip'] ?? null)) {
            if ($this->usesManualAddress($metrics)) {
                return $this->manualAddress($metrics);
            }

            return new NetworkDiagnosis(
                code: 'gateway_missing',
                title: 'Default Gateway Missing',
                severity: 'high',
                summary: 'The adapter has an IPv4 address but Windows does not currently report a default IPv4 gateway.',
                details: ['Without a default gateway, traffic usually cannot leave the local network.'],
                recommendedAction: 'Try renewing address to request the gateway configuration again. NetRescue verifies whether the gateway returns.',
                recoveryActionCode: 'renew_dhcp',
            );
        }

        if (in_array('unavailable', $metrics['probe_states'] ?? [], true)) {
            return new NetworkDiagnosis(
                code: 'probe_unavailable',
                title: 'Connectivity Test Did Not Finish',
                severity: 'low',
                summary: 'A Windows connectivity test did not return a completed result. This does not establish a gateway or DNS outage.',
                details: ['Completed measurements are retained. An unavailable test is not shown as a failed network response.'],
                recommendedAction: 'Review the recorded test error and run Check again. No repair is indicated by an incomplete test.',
            );
        }

        if (!($metrics['gateway_reachable'] ?? false) && !($metrics['internet_reachable'] ?? false)) {
            if ($this->usesManualAddress($metrics)) {
                return $this->manualAddress($metrics);
            }

            return new NetworkDiagnosis(
                code: 'gateway_unreachable',
                title: 'Possible Local Gateway / Path Issue',
                severity: 'high',
                summary: 'The configured gateway did not answer ping, and external TCP connectivity tests also failed.',
                details: [
                    'A local link, access point, router, firewall, or gateway problem is possible; these tests do not prove which device failed.',
                    'The external TCP failure is retained as evidence. Name lookup was not tested because the external path failed.',
                ],
                recommendedAction: 'Try renewing address for a possible local configuration problem. Renewal cannot remove a firewall block or fix a router outage; NetRescue checks whether it helps.',
                recoveryActionCode: 'renew_dhcp',
            );
        }

        if (!($metrics['internet_reachable'] ?? false)) {
            return new NetworkDiagnosis(
                code: 'wan_unreachable',
                title: 'Possible WAN / ISP Connectivity Issue',
                severity: 'high',
                summary: 'The local gateway is reachable, but NetRescue could not establish external TCP connectivity to its configured test targets.',
                details: [
                    'The local LAN path is available.',
                    'Automatic local repair is not appropriate because the failure may be upstream of this computer.',
                ],
                recommendedAction: 'Check the router WAN status or contact the network/Internet administrator, then re-test.',
            );
        }

        if (!($metrics['dns_operational'] ?? false)) {
            return new NetworkDiagnosis(
                code: 'dns_failure',
                title: 'DNS Resolution Failure',
                severity: 'moderate',
                summary: 'External TCP connectivity is available, but the configured Windows DNS resolver could not resolve the test domain.',
                details: [
                    ($metrics['gateway_reachable'] ?? false) ? 'Gateway ping passed.' : 'Gateway ping did not answer, but the external TCP test passed.',
                    'External TCP connectivity passed.',
                    'The DNS test failed independently, so this is treated as a DNS-specific condition.',
                ],
                recommendedAction: 'Try clearing DNS cache to discard cached lookup records, then test again. This does not replace the configured DNS servers.',
                recoveryActionCode: 'flush_dns',
            );
        }

        if (!($metrics['gateway_reachable'] ?? false)) {
            return new NetworkDiagnosis(
                code: 'gateway_ping_unanswered',
                title: 'Gateway Ping Unanswered; Internet Available',
                severity: 'low',
                summary: 'The gateway did not answer ICMP ping, but external TCP connectivity and DNS resolution passed.',
                details: ['Ping alone does not prove that the router or connection is down.', 'Gateway response time could not be measured.'],
                recommendedAction: 'No local repair is indicated. Check whether the router or firewall allows ICMP if gateway latency monitoring is needed.',
            );
        }

        $gatewayLatency = $metrics['gateway_latency_ms'] ?? null;
        if (is_numeric($gatewayLatency) && (float) $gatewayLatency > $latencyWarningMs) {
            return new NetworkDiagnosis(
                code: 'high_latency',
                title: 'Elevated Local Network Latency',
                severity: 'low',
                summary: sprintf('The network is operational, but gateway response time (%.1f ms) exceeds the NetRescue warning threshold of %d ms.', (float) $gatewayLatency, $latencyWarningMs),
                details: ['No destructive recovery is performed. NetRescue will continue monitoring for persistence or improvement.'],
                recommendedAction: 'Observe the connection, reduce local congestion if applicable, and re-test.',
            );
        }

        return new NetworkDiagnosis(
            code: 'healthy',
            title: 'Network Operational',
            severity: 'none',
            summary: 'Gateway connectivity, external connectivity, DNS resolution, and local response time are within the configured NetRescue checks.',
            details: ['No recovery action is required.'],
        );
    }

    private function usesManualAddress(array $metrics): bool
    {
        return ($metrics['address_source'] ?? 'unknown') === 'static';
    }

    private function manualAddress(array $metrics): NetworkDiagnosis
    {
        $name = $metrics['interface_name'] ?? null ?: 'This adapter';

        return new NetworkDiagnosis(
            code: 'static_configuration',
            title: 'Manual Address Not Reaching the Router',
            severity: 'high',
            summary: $name.' uses a manual address, so renewing it will not ask the router for a new one.',
            details: [
                'The address was set by a person, not received from the router.',
                'ipconfig /renew does not replace a manual address.',
            ],
            recommendedAction: 'A person should set '.$name.' to obtain an address automatically, or correct the manual address and router address.',
        );
    }
}
