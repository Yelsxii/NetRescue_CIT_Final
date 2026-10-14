<?php

namespace App\Services\Network;

use Symfony\Component\Process\Process;
use Throwable;

class RecoveryCommandRunner
{
    public function __construct(private readonly NetworkProbeService $probe) {}

    public function run(string $actionCode, string $incidentCode, ?string $interfaceName): array
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return ['successful' => false, 'output' => '', 'error' => 'Recovery requires Windows.', 'exit_code' => null];
        }
        if (in_array($actionCode, ['enable_adapter', 'restart_adapter', 'renew_dhcp'], true) && blank($interfaceName)) {
            return ['successful' => false, 'output' => '', 'error' => 'The measured adapter name is missing.', 'exit_code' => null];
        }
        if (in_array($actionCode, ['enable_adapter', 'restart_adapter'], true)) {
            return $actionCode === 'enable_adapter'
                ? $this->probe->enableAdapter($interfaceName)
                : $this->probe->restartAdapter($interfaceName);
        }

        // DHCP commands target the measured adapter, not every disconnected adapter.
        $commands = match ($actionCode) {
            'flush_dns' => [['ipconfig.exe', '/flushdns']],
            'renew_dhcp' => $incidentCode === 'dhcp_issue'
                ? [['ipconfig.exe', '/release', $interfaceName], ['ipconfig.exe', '/renew', $interfaceName]]
                : [['ipconfig.exe', '/renew', $interfaceName]],
            default => throw new \RuntimeException('Unsupported recovery action.'),
        };
        $output = [];
        $exitCode = null;
        try {
            foreach ($commands as $command) {
                $process = new Process($command);
                $process->setTimeout((float) config('netrescue.timeouts.recovery_seconds', 35));
                $process->run();
                $exitCode = $process->getExitCode();
                $output[] = implode(' ', $command)."\n".trim($process->getOutput());
                if (! $process->isSuccessful()) {
                    $error = trim($process->getErrorOutput()) ?: 'Command returned exit code '.$exitCode.'. See command output.';
                    return ['successful' => false, 'output' => implode("\n\n", $output), 'error' => $error, 'exit_code' => $exitCode];
                }
            }
            return ['successful' => true, 'output' => implode("\n\n", $output), 'error' => '', 'exit_code' => $exitCode];
        } catch (Throwable $e) {
            return ['successful' => false, 'output' => implode("\n\n", $output), 'error' => $e->getMessage(), 'exit_code' => $exitCode];
        }
    }
}
