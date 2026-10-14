<?php

namespace App\Services\Network;

use App\Models\HealthCheck;
use App\Models\Incident;

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
                && $after->local_ip
                && ! str_starts_with((string) $after->local_ip, '169.254.')
                && $after->gateway_ip,
            'gateway_unreachable' => ! $before->gateway_reachable && $after->gateway_reachable,
            'gateway_missing' => filled($after->gateway_ip) && $after->gateway_reachable,
            'ip_configuration_missing' => filled($after->local_ip) && ! str_starts_with((string) $after->local_ip, '169.254.'),
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

            return ['renew_dhcp', 'Renew the address so Windows receives DNS servers again.'];
        }

        if ($actionCode === 'renew_dhcp' && in_array($incident->code, ['gateway_unreachable', 'gateway_missing', 'ip_configuration_missing', 'dhcp_issue'], true) && filled($after->interface_name)) {
            return ['restart_adapter', 'Renew did not restore the connection. Restart '.$name.', then measure again.'];
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
