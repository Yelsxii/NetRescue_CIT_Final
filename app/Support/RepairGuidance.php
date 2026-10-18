<?php

namespace App\Support;

use App\Models\Incident;
use App\Models\RecoveryAction;

class RepairGuidance
{
    public static function for(Incident $incident): array
    {
        $previous = $incident->latestRecovery();
        $adapter = $incident->latestHealthCheck?->interface_name ?: 'the measured adapter';
        [$label, $actionTitle, $purpose, $limitation, $consequence, $attention] = match ($incident->recovery_action_code) {
            'enable_adapter' => ['Try enabling adapter', 'Enable adapter',
                'Turn on '.$adapter.' and verify its connection.',
                'Enabling an adapter cannot fix a missing driver, disconnected cable, or router outage.',
                'The adapter may take time to reconnect.', ''],
            'restart_adapter' => ['Try restarting adapter', 'Restart adapter',
                'Restart '.$adapter.' and verify whether the connection recovers.',
                'A restart cannot remove a firewall block or resolve a router or provider outage.',
                'This briefly disconnects '.$adapter.'.',
                'This briefly disconnects '.$adapter.'.'],
            'flush_dns' => ['Try clearing DNS cache', 'Clear DNS cache',
                'Clear cached lookup records and test name resolution again.',
                'This does not replace DNS server settings or fix an unreachable DNS server.',
                'The next name lookup will need a fresh answer.', ''],
            'renew_dhcp' => [$incident->code === 'dhcp_issue' ? 'Try release and renew' : 'Try renewing address',
                $incident->code === 'dhcp_issue' ? 'Release and renew address' : 'Renew address',
                $incident->code === 'dhcp_issue'
                    ? 'Release the current address and request new DHCP configuration for '.$adapter.'.'
                    : 'Request DHCP configuration for '.$adapter.' and verify its address and gateway.',
                'Renewal cannot remove a firewall block, resolve a router outage, or replace manual DNS settings.',
                $incident->code === 'dhcp_issue' ? 'Releasing the address can temporarily interrupt connectivity.' : 'Connectivity may pause while Windows renews the address.',
                $incident->code === 'dhcp_issue' ? 'Releasing the address can temporarily interrupt connectivity.' : 'Connectivity may pause while Windows renews the address.'],
            default => ['Review repair', 'Review repair', 'Review the current condition before requesting another action.',
                'No supported repair is currently available.', '', ''],
        };
        [$previousStatus, $previousSummary] = self::previousResult($previous);
        [$manualTitle, $manualStep] = self::manualGuidance($incident, $previous);

        return [
            'button_label' => $label,
            'action_title' => $actionTitle,
            'purpose' => $purpose,
            'limitation' => $limitation,
            'consequence' => $consequence,
            'attention' => $attention,
            'busy_text' => $label.' — checking the condition and verifying the result…',
            'confirmation_title' => $previous ? 'Review another repair attempt' : 'Review this repair attempt',
            'previous_result' => $previous?->outcomeSummary(),
            'previous_status' => $previousStatus,
            'previous_summary' => $previousSummary,
            'previous_request_id' => $previous?->id ?? 0,
            'is_follow_up' => $previous !== null,
            'manual_title' => $manualTitle,
            'manual_step' => $manualStep,
        ];
    }

    public static function result(RecoveryAction $action): array
    {
        [$status, $summary] = self::previousResult($action);

        return ['status' => $status, 'summary' => $summary];
    }

    private static function previousResult(?RecoveryAction $previous): array
    {
        if (! $previous) return ['', ''];

        if ($previous->execution_status === 'skipped') {
            return match ($previous->verification_status) {
                'already_recovered' => ['Already recovered', 'No command was run; the pre-repair check already showed recovery.'],
                'unread' => ['Reading incomplete', 'No command was run because the pre-repair reading did not finish.'],
                'adapter_changed' => ['Adapter changed', 'No command was run because Windows selected a different adapter.'],
                default => ['Condition changed', 'No command was run because the pre-repair check found a different condition.'],
            };
        }

        if ($previous->commandTimedOut()) {
            $verification = ! $previous->completed_at ? 'Verification is pending.' : match ($previous->verification_status) {
                'observed_recovery' => 'A check observed recovery without verifying its cause.',
                'unread' => 'The follow-up reading was incomplete.',
                'adapter_changed' => 'The follow-up measured a different adapter.',
                'changed_condition' => 'The follow-up measured a different condition.',
                default => 'Recovery was not verified.',
            };
            return ['Command timed out', 'Command completion was not confirmed before the time limit. '.$verification];
        }

        if (! $previous->completed_at) {
            return match ($previous->execution_status) {
                'completed' => ['Verification pending', 'The command completed; follow-up verification has not finished.'],
                'failed' => ['Verification pending', 'The command did not complete successfully; follow-up verification has not finished.'],
                default => ['Result pending', 'Command completion and recovery have not been recorded yet.'],
            };
        }

        if ($previous->beforeHealthCheck?->diagnosis_code === 'healthy') {
            return ['Already healthy', 'The connection was healthy before this attempt; recovery was not established by the attempt.'];
        }

        if ($previous->verification_status === 'observed_recovery') {
            return ['Recovery observed', 'A check observed recovery; the repair was not verified as its cause.'];
        }

        if ($previous->execution_status === 'failed') {
            $verification = match ($previous->verification_status) {
                'unread' => 'the follow-up reading was incomplete.',
                'adapter_changed' => 'the follow-up measured a different adapter, so target recovery remains unverified.',
                'changed_condition' => 'the follow-up measured a different condition, so recovery remains unverified.',
                default => 'recovery was not verified.',
            };
            return ['Command unsuccessful', 'The command did not complete successfully; '.$verification];
        }

        if ($previous->success) {
            return ['Recovery verified', $previous->afterHealthCheck && $previous->afterHealthCheck->diagnosis_code !== 'healthy'
                ? 'The target condition cleared after repair; another condition remains.'
                : 'The follow-up check verified that the target condition cleared after repair.'];
        }

        return match ($previous->verification_status) {
            'unread' => ['Reading incomplete', 'The follow-up reading did not finish; recovery remains unverified.'],
            'adapter_changed' => ['Adapter changed', 'The follow-up measured a different adapter; recovery of the target remains unverified.'],
            'changed_condition' => ['Condition changed', 'The follow-up measured a different condition; recovery was not verified.'],
            'not_verified' => $previous->execution_status === 'completed'
                ? ['Condition remains', 'The command completed, but the follow-up still found the target condition.']
                : ['Recovery unverified', 'Recovery was not verified; command completion was not recorded separately.'],
            default => ['Recovery unverified', 'The recorded result does not verify recovery of the target condition.'],
        };
    }

    private static function manualGuidance(Incident $incident, ?RecoveryAction $previous): array
    {
        if ($previous && ! $previous->completed_at) {
            return ['Verification pending', 'Wait for the current request to finish before attempting another repair.'];
        }
        if ($previous && in_array($previous->verification_status, ['unread', 'adapter_changed'], true)) {
            return ['Check required', $previous->verification_status === 'adapter_changed'
                ? 'Check this incident\'s adapter again before requesting another repair.'
                : 'Review the recorded reading error, then check this incident\'s adapter again.'];
        }

        return match ($incident->code) {
            'healthy' => ['No repair required', 'Continue monitoring the connection.'],
            'gateway_ping_unanswered' => ['No repair required', 'Internet and DNS tests passed. Review router or firewall ICMP policy only if gateway latency monitoring is required.'],
            'high_latency', 'slow_network' => ['No repair required', 'Observe the connection and review local network traffic if the slow response persists.'],
            'configuration_unreadable', 'probe_unavailable' => ['Check required', 'Review the recorded test error, then run Check again. An incomplete reading does not confirm a network outage.'],
            'dns_failure' => ['Manual action required', data_get($incident->latestHealthCheck?->probe_meta, 'dns_source') === 'static'
                ? 'Verify the manually configured DNS servers against the approved settings, then run Check again.'
                : 'Review the DNS server, VPN and firewall settings, then run Check again.'],
            'gateway_unreachable' => ['Manual action required', 'Check the router connection and firewall policy, then run Check again.'],
            'wan_unreachable', 'internet_unreachable' => ['Manual action required', 'Review the router WAN status and firewall policy with the network administrator, then run Check again.'],
            'adapter_disabled' => ['Manual action required', 'Enable the adapter in Windows network settings. Review administrator permissions if Windows denies access.'],
            'adapter_disconnected' => ['Manual action required', 'Reconnect the Ethernet cable or join the Wi-Fi network, then run Check again.'],
            'adapter_unavailable' => ['Manual action required', 'Check the adapter installation and driver in Windows, then run Check again.'],
            'static_configuration' => ['Manual action required', 'Verify the approved manual IPv4 address, subnet and gateway settings, then run Check again.'],
            'dhcp_issue', 'ip_configuration_missing', 'gateway_missing' => ['Manual action required', 'Check the router DHCP service and the adapter address settings, then run Check again.'],
            'unsupported_platform' => ['Manual action required', 'Run NetRescue on the Windows computer whose connection you want to monitor.'],
            'device_offline', 'device_unreachable' => ['Manual action required', 'Check that the computer is powered on and connected to the network.'],
            default => ['Manual action required', 'Review the recorded condition and network settings, then run Check again.'],
        };
    }
}
