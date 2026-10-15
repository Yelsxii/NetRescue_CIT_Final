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
            $resolution = $attempt && in_array($attempt->verification_status, ['pending', 'unread'], true)
                ? 'This incident remains open because the current condition has not been verified after the repair request.'
                : match ($incident->code) {
                'dhcp_issue' => 'No usable DHCP address obtained; a 169.254 fallback address was reported.',
                'ip_configuration_missing' => 'No usable IPv4 address was reported.',
                'gateway_missing' => 'No default IPv4 gateway was reported.',
                default => 'This condition remains open. '.$incident->plainDetail(),
            };
        } elseif ($incident->resolution_kind === 'already_recovered') {
            $resolution = 'Recovery observed in the latest pre-repair check; that request ran no command.';
        } elseif ($attempt?->beforeHealthCheck?->diagnosis_code === 'healthy') {
            // Describe old records such as NR-00019 without changing their stored success flag.
            $resolution = 'The connection was already healthy before the repair attempt.';
        } elseif ($incident->resolution_kind === 'superseded') {
            $resolution = 'Closed because the check recorded a different condition; recovery was not confirmed.';
        } elseif ($attempt?->success) {
            $resolution = 'The follow-up check verified that the target condition cleared after the repair.';
        } elseif ($check?->diagnosis_code === 'healthy') {
            $resolution = 'Recovery observed by a check; a repair was not verified as the cause.';
        } elseif ($check) {
            $resolution = 'The check recorded a different condition; complete recovery was not confirmed.';
        } else {
            $resolution = 'This record was closed; an exact closing measurement is not available.';
        }

        return ['resolution' => $resolution, 'repair' => $repair];
    }
}
