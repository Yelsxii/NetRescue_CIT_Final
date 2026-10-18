<?php

namespace App\Services\Network;

use App\Models\HealthCheck;
use App\Models\Incident;
use App\Support\Ipv4Address;

class RecoveryPlan
{
    public function cleared(string $incidentCode, ?HealthCheck $before, HealthCheck $after): bool
    {
        if (! $before) {
            return false;
        }

        return match ($incidentCode) {
            'dns_failure' => ! $before->dns_operational && $after->dns_operational,
            'dhcp_issue' => str_starts_with((string) $before->local_ip, '169.254.')
                && Ipv4Address::usable($after->local_ip)
                && Ipv4Address::usable($after->gateway_ip),
            'gateway_unreachable' => ! $before->gateway_reachable && $after->gateway_reachable,
            'gateway_missing' => filled($after->gateway_ip) && $after->gateway_reachable,
            'ip_configuration_missing' => Ipv4Address::usable($after->local_ip),
            'adapter_disabled' => ($after->probe_meta['adapter_state'] ?? null) === 'up',
            default => false,
        };
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    public function nextStep(Incident $incident, string $actionCode, HealthCheck $after): array
    {
        $name = $after->interface_name ?: 'the adapter';
        $dnsSource = $after->probe_meta['dns_source'] ?? 'unknown';

        if ($incident->code === 'dns_failure' && $actionCode === 'flush_dns') {
            if ($dnsSource === 'static') {
                return [null, 'Name lookup still failed. The DNS server address is set manually, so renew will not replace it. A person should correct that DNS server or set DNS to automatic.'];
            }

            if ($dnsSource !== 'dhcp' || ($after->probe_meta['address_source'] ?? 'unknown') !== 'dhcp' || blank($after->interface_name)) {
                return [null, 'Name lookup still failed. DHCP addressing and automatic DNS were not both confirmed, so renewal is not appropriate. A person should review the DNS servers and address settings, then re-test.'];
            }

            return ['renew_dhcp', 'Renew the address so Windows receives DNS servers again.'];
        }

        if ($incident->code === 'dns_failure' && $actionCode === 'renew_dhcp') {
            return [null, 'Name lookup still failed after clearing the cache and renewing DHCP. A person should check the DNS server, router DNS settings, VPN, or firewall, then re-test. Repeating renewal has not been shown to help.'];
        }

        if ($actionCode === 'renew_dhcp' && in_array($incident->code, ['gateway_unreachable', 'gateway_missing', 'ip_configuration_missing', 'dhcp_issue'], true) && filled($after->interface_name)) {
            return ['restart_adapter', 'Renewal did not restore the connection. You can try restarting '.$name.' as another attempt. This briefly disconnects the adapter and may not fix the cause; review and confirm before continuing.'];
        }

        if ($actionCode === 'restart_adapter') {
            return [null, 'Restarting the adapter did not restore the connection. Check the cable, Wi-Fi, or the router. Resetting Winsock or the IP stack disconnects the computer and needs a person to confirm it.'];
        }

        if ($actionCode === 'enable_adapter') {
            return [null, 'The adapter could not be enabled. A person should enable '.$name.' in Windows network settings.'];
        }

        return [$incident->recovery_action_code, $incident->recommended_action];
    }
}
