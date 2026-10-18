<?php

namespace App\Support;

use App\Models\Incident;

class RepairGuidance
{
    public static function for(Incident $incident): array
    {
        $previous = $incident->latestRecovery();
        $adapter = $incident->latestHealthCheck?->interface_name ?: 'the measured adapter';
        [$label, $purpose, $limitation, $consequence] = match ($incident->recovery_action_code) {
            'enable_adapter' => ['Try enabling adapter',
                'Ask Windows to turn on '.$adapter.' and check whether it reconnects.',
                'Enabling an adapter cannot fix a missing driver, disconnected cable, or router outage.',
                'The adapter may take time to reconnect.'],
            'restart_adapter' => ['Try restarting adapter',
                'Restart '.$adapter.' to try reconnecting its local network connection.',
                'A restart may help an adapter problem. It cannot remove a firewall block or fix a router or provider outage.',
                'This briefly disconnects '.$adapter.'. Wait for the automatic follow-up checks.'],
            'flush_dns' => ['Try clearing DNS cache',
                'Clear cached name-lookup records, then test the configured domain again.',
                'This does not replace DNS server settings or fix an unreachable DNS server.',
                'The next name lookup will need a fresh answer.'],
            'renew_dhcp' => [$incident->code === 'dhcp_issue' ? 'Try release and renew' : 'Try renewing address',
                $incident->code === 'dhcp_issue'
                    ? 'Release and request a fresh DHCP address and gateway for '.$adapter.'.'
                    : 'Request DHCP configuration again for '.$adapter.' and check its address and gateway.',
                'Renewal may help a local address problem. It cannot remove a firewall block, fix a router outage, or replace manual DNS settings.',
                $incident->code === 'dhcp_issue' ? 'Releasing the address can temporarily interrupt connectivity.' : 'Connectivity may pause while Windows renews the address.'],
            default => ['Review repair', 'Review the current condition before requesting another action.',
                'No supported repair is currently available.', ''],
        };

        return [
            'button_label' => $label,
            'purpose' => $purpose,
            'limitation' => $limitation,
            'consequence' => $consequence,
            'busy_text' => $label.' — checking the condition and verifying the result…',
            'confirmation_title' => $previous ? 'Review another repair attempt' : 'Review this repair attempt',
            'previous_result' => $previous?->outcomeSummary(),
            'previous_request_id' => $previous?->id ?? 0,
            'is_follow_up' => $previous !== null,
        ];
    }
}
