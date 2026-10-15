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
        $commandSeconds = (float) config('netrescue.timeouts.recovery_seconds', 35);

        // Two complete readings, bounded commands, reconnect delay, and request overhead.
        return max(180, (int) ceil(2 * $this->measurementSeconds() + $commands * $commandSeconds + 3 + 30));
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
