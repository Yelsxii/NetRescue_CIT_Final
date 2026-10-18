<?php

namespace App\Support;

use App\Models\Incident;

class IncidentStory
{
    /** The table, full page and modal all render these same two statements. */
    public static function for(Incident $incident): array
    {
        $attempt = $incident->latestRecovery();
        $check = $incident->closingEvidence();
        $repair = $attempt?->outcomeSummary() ?? 'No repair was run.';

        if ($incident->is_active) {
            $resolution = $attempt && in_array($attempt->verification_status, ['pending', 'unread', 'adapter_changed'], true)
                ? 'This incident remains open because the current condition has not been verified after the repair request.'
                : match ($incident->code) {
                    'dhcp_issue' => 'No usable DHCP address obtained; a 169.254 fallback address was reported.',
                    'ip_configuration_missing' => 'No usable IPv4 address was reported.',
                    'gateway_missing' => 'No default IPv4 gateway was reported.',
                    default => 'This condition remains open. '.$incident->plainDetail(),
                };
        } elseif ($incident->resolution_kind === 'already_recovered') {
            $resolution = 'The pre-repair check confirmed that the condition had already cleared. No repair command was run.';
        } elseif ($attempt?->beforeHealthCheck?->diagnosis_code === 'healthy') {
            // Describe old records such as NR-00019 without changing their stored success flag.
            $resolution = 'The connection was already healthy before the repair attempt.';
        } elseif ($incident->resolution_kind === 'superseded') {
            $resolution = 'A subsequent check identified a different condition. Recovery from this incident was not confirmed.';
        } elseif ($attempt?->success) {
            $resolution = 'The follow-up check verified that the target condition cleared after the repair.';
        } elseif ($check?->diagnosis_code === 'healthy') {
            $resolution = $attempt
                ? 'A subsequent check confirmed recovery. The recorded repair was not verified as the cause.'
                : 'A subsequent check confirmed recovery. No repair command was run.';
        } elseif ($check) {
            $resolution = 'The check identified a different condition. Complete recovery was not confirmed.';
        } else {
            $resolution = 'The incident is closed. An exact closing measurement is not available.';
        }

        return ['resolution' => $resolution, 'repair' => $repair];
    }
}
