<?php

namespace App\Support;

use App\Models\HealthCheck;
use App\Models\Incident;

class CheckStory
{
    public static function points(iterable $checks): \Illuminate\Support\Collection
    {
        return collect($checks)
            ->filter(fn (HealthCheck $check) => $check->readingFinished())
            ->map(fn (HealthCheck $check) => (object) [
            'checked_at' => $check->checked_at,
            'gateway' => $check->gateway_latency_ms,
            'internet' => $check->internet_latency_ms,
            'dns' => $check->dns_latency_ms,
            'gateway_ok' => $check->probeWasRun('gateway') ? $check->gateway_reachable : null,
            'internet_ok' => $check->probeWasRun('internet') ? $check->internet_reachable : null,
            'dns_ok' => $check->probeWasRun('dns') ? $check->dns_operational : null,
            'local_ip' => $check->local_ip,
            'source' => $check->source,
            'title' => $check->diagnosis_title,
        ])->values();
    }

    public static function mark(mixed $ok, mixed $ms, int $warningMs): array
    {
        if ($ok === false) {
            return ['code' => 'fail', 'label' => 'FAIL', 'detail' => 'Did not answer'];
        }

        if (is_numeric($ms) && (float) $ms > $warningMs) {
            return ['code' => 'slow', 'label' => 'SLOW', 'detail' => number_format((float) $ms, 1).' ms'];
        }

        if ($ok) {
            return [
                'code' => 'pass',
                'label' => 'PASS',
                'detail' => is_numeric($ms) ? number_format((float) $ms, 1).' ms' : 'Answered',
            ];
        }

        return ['code' => 'none', 'label' => '—', 'detail' => 'Not tested'];
    }

    public static function plainCode(string $code): string
    {
        return match ($code) {
            'high_latency', 'slow_network' => 'Slow response',
            'dns_failure' => 'Name lookup failure',
            'wan_unreachable', 'internet_unreachable' => 'Internet path down',
            'gateway_unreachable' => 'Gateway down',
            'gateway_ping_unanswered' => 'Gateway ping unanswered',
            'probe_unavailable' => 'Connectivity test unavailable',
            'dhcp_issue' => 'No usable address',
            'adapter_disabled' => 'Adapter turned off',
            'adapter_disconnected' => 'No cable or Wi-Fi link',
            'static_configuration' => 'Manual address',
            'device_offline', 'device_unreachable' => 'Computer not responding',
            default => 'Another network condition',
        };
    }

    public static function sourceLabel(mixed $check): string
    {
        $source = is_object($check) ? ($check->source ?? null) : $check;

        return match ($source) {
            'automatic' => 'Automatic check',
            'manual' => 'Manual check',
            'recovery_before' => 'Check before repair',
            'recovery_after' => 'Check after repair',
            default => 'Stored check',
        };
    }

    public static function focus(string $code, ?HealthCheck $check, int $warningMs): ?string
    {
        if (! $check) {
            return null;
        }

        return match ($code) {
            'high_latency', 'slow_network' => self::timed('Gateway', $check->gateway_reachable, $check->gateway_latency_ms, $warningMs),
            'dns_failure' => $check->dns_operational
                ? self::timed('Name lookup', true, $check->dns_latency_ms, $warningMs)
                : ($check->probeWasRun('dns')
                    ? 'Windows could not resolve'.($check->dns_test_domain ? ' '.$check->dns_test_domain : ' the configured test domain').'.'
                    : 'Name resolution was not tested in this reading.'),
            'wan_unreachable', 'internet_unreachable' => $check->internet_reachable
                ? self::timed('Internet', true, $check->internet_latency_ms, $warningMs)
                : ($check->probeWasRun('internet') ? 'Connections to the configured external test targets failed.' : 'External connectivity was not tested in this reading.'),
            'gateway_unreachable' => $check->gateway_reachable
                ? self::timed('Gateway', true, $check->gateway_latency_ms, $warningMs)
                : ($check->probeWasRun('gateway') ? 'The configured gateway did not respond to the connectivity test.' : 'Gateway connectivity was not tested in this reading.'),
            'dhcp_issue' => $check->local_ip
                ? 'This computer reported address '.$check->local_ip.'.'
                : 'This computer had no usable network address.',
            'device_offline', 'device_unreachable' => 'This computer was not responding.',
            'adapter_disabled' => match (data_get($check->probe_meta, 'adapter_state', $check->diagnosis_code === 'healthy' ? 'up' : null)) {
                'up' => 'Windows reported that '.($check->interface_name ?: 'the network adapter').' was enabled.',
                'disabled' => 'Windows reported that '.($check->interface_name ?: 'the network adapter').' was disabled.',
                default => $check->diagnosis_code === 'adapter_disabled' ? 'Windows reported that the network adapter was disabled.' : 'The adapter state was not available in this reading.',
            },
            'adapter_disconnected' => $check->diagnosis_code === 'adapter_disconnected'
                ? 'The adapter was enabled, but no network link was detected.'
                : ($check->diagnosis_code === 'healthy' ? 'A network link was available.' : $check->diagnosis_summary),
            'ip_configuration_missing' => Ipv4Address::usable($check->local_ip)
                ? 'Windows reported IPv4 address '.$check->local_ip.'.'
                : 'No usable IPv4 address was reported for this adapter.',
            'gateway_missing' => filled($check->gateway_ip)
                ? 'Windows reported default IPv4 gateway '.$check->gateway_ip.'.'
                : 'Windows did not report a default IPv4 gateway for this adapter.',
            default => $check->diagnosis_summary,
        };
    }

    public static function context(string $code, ?HealthCheck $check, int $warningMs): array
    {
        if (! $check) {
            return [];
        }

        $lines = [];

        if (! in_array($code, ['high_latency', 'slow_network', 'gateway_unreachable'], true)) {
            $lines[] = self::timed('Gateway', $check->gateway_reachable, $check->gateway_latency_ms, $warningMs);
        }

        if (! in_array($code, ['wan_unreachable', 'internet_unreachable'], true)) {
            $lines[] = self::timed('Internet', $check->internet_reachable, $check->internet_latency_ms, $warningMs);
        }

        if ($code !== 'dns_failure') {
            $lines[] = self::timed('Name lookup', $check->dns_operational, $check->dns_latency_ms, $warningMs);
        }

        return array_values(array_filter($lines));
    }

    public static function probeMark(?HealthCheck $check, string $probe, int $warningMs): array
    {
        if (! $check || $check->diagnosis_code === 'configuration_unreadable') {
            return ['code' => 'none', 'label' => 'Not measured', 'detail' => 'Reading unavailable'];
        }
        $state = data_get($check->probe_meta, 'probe_states.'.$probe);
        if ($state === 'unavailable') {
            return ['code' => 'none', 'label' => 'Not measured', 'detail' => 'Windows test result unavailable'];
        }
        if (! $check->readingFinished() && ! in_array($state, ['passed', 'failed', 'skipped'], true)) {
            return ['code' => 'none', 'label' => 'Not measured', 'detail' => 'Reading unavailable'];
        }
        if (! $check->probeWasRun($probe)) {
            return ['code' => 'none', 'label' => 'Not tested', 'detail' => 'Earlier network prerequisite did not pass'];
        }
        $field = $probe === 'dns' ? 'dns_operational' : $probe.'_reachable';
        return self::mark($check->$field, $check->{$probe.'_latency_ms'}, $warningMs);
    }

    public static function repairLine(Incident $incident): string
    {
        return $incident->story()['repair'];
    }

    private static function timed(string $label, mixed $ok, mixed $ms, int $warningMs): ?string
    {
        if ($ok === false) {
            return $label.' did not answer.';
        }

        if (! is_numeric($ms)) {
            return $ok ? $label.' answered, with no response time stored.' : null;
        }

        $text = number_format((float) $ms, 1).' ms';

        if ((float) $ms > $warningMs) {
            return $label.' answered in '.$text.', above the '.$warningMs.' ms warning.';
        }

        return $label.' answered in '.$text.', under the '.$warningMs.' ms warning.';
    }
}
