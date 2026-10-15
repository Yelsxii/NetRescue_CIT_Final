<?php

namespace App\Services\Network;

use App\Models\MonitorSetting;
use RuntimeException;

class NetworkRequestTimeout
{
    public function prepareCheck(): void
    {
        $this->apply($this->checkSeconds());
    }

    public function prepareRecovery(string $actionCode, string $incidentCode): void
    {
        $this->apply($this->recoverySeconds($actionCode, $incidentCode));
    }

    public function checkSeconds(): int
    {
        return max(60, (int) ceil($this->measurementSeconds() + 30));
    }

    public function recoverySeconds(string $actionCode, string $incidentCode): int
    {
        $commands = $actionCode === 'renew_dhcp' && $incidentCode === 'dhcp_issue' ? 2 : 1;
        $commandSeconds = $this->commandSeconds($actionCode);

        $attempts = max(1, min(3, (int) config('netrescue.verification.attempts', 3)));
        $delay = max(0, min(5, (float) config('netrescue.verification.retry_delay_seconds', 1)));
        // One fresh precheck and up to three automatic follow-ups. These are
        // worst-case allowances, not delays added to a successful repair.
        $standard = (1 + $attempts) * $this->measurementSeconds()
            + $commands * $commandSeconds + ($attempts - 1) * $delay;
        // Include an in-flight reading at the end of the elapsed reconnect window.
        $reconnect = in_array($actionCode, ['enable_adapter', 'restart_adapter'], true)
            ? 2 * $this->measurementSeconds() + $commandSeconds + $this->reconnectSeconds() : 0;
        return max(180, (int) ceil(max($standard, $reconnect) + 30));
    }

    public function commandSeconds(string $actionCode): float
    {
        if (! in_array($actionCode, ['enable_adapter', 'restart_adapter'], true)) {
            return (float) config('netrescue.timeouts.recovery_seconds', 35);
        }
        $seconds = (float) config('netrescue.timeouts.adapter_recovery_seconds', 60);
        return is_finite($seconds) && $seconds > 0 ? min(120, $seconds) : 60.0;
    }

    public function reconnectSeconds(): float
    {
        $seconds = (float) config('netrescue.verification.reconnect_window_seconds', 60);
        return is_finite($seconds) && $seconds >= 0 ? min(120, $seconds) : 60.0;
    }

    private function measurementSeconds(): float
    {
        $targets = MonitorSetting::current()->internet_targets ?: config('netrescue.internet_targets', []);

        return (float) config('netrescue.timeouts.powershell_seconds', 20)
            + (float) config('netrescue.timeouts.ping_seconds', 5)
            + count($targets) * (float) config('netrescue.timeouts.socket_seconds', 2.5)
            + (float) config('netrescue.timeouts.dns_seconds', 6);
    }

    private function apply(int $seconds): void
    {
        $current = (int) ini_get('max_execution_time');
        if ($current === 0) {
            return;
        }
        if (function_exists('set_time_limit') && @set_time_limit(max($current, $seconds))) {
            return;
        }
        if ($current >= $seconds) {
            return;
        }

        throw new RuntimeException('PHP allows only '.$current.' seconds for this request; the network operation needs '.$seconds.' seconds. Enable set_time_limit or increase the serving PHP execution limit before retrying. No repair command was run.');
    }
}
