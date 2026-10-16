<?php

namespace App\Services\Network;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Throwable;

class NetworkProbeService
{
    public function isSupportedPlatform(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public function collectConfiguration(?string $interfaceName = null, ?float $configurationTimeout = null): array
    {
        if (!$this->isSupportedPlatform()) {
            return $this->emptyConfiguration('NetRescue network probes require Windows 10 or Windows 11.', false);
        }

        $scriptPath = resource_path('powershell/read-configuration.ps1');
        if (! is_readable($scriptPath)) {
            return $this->emptyConfiguration('The Windows configuration reader script is missing.');
        }

        // Keep the larger script out of cmd.exe's command-line length limit.
        $command = [$this->windowsTool('WindowsPowerShell\\v1.0\\powershell.exe'),
            '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $scriptPath];
        if (filled($interfaceName)) {
            $command = array_merge($command, ['-InterfaceName', $this->adapterName($interfaceName)]);
        }
        $timeout = (float) config('netrescue.timeouts.powershell_seconds', 20);
        if ($configurationTimeout !== null) {
            if (! is_finite($configurationTimeout) || $configurationTimeout <= 0) {
                throw new \InvalidArgumentException('The configuration timeout must be a positive finite number.');
            }
            $timeout = min($timeout, $configurationTimeout);
        }
        $result = $this->runProcess($command, $timeout);

        if (!$result['successful']) {
            return $this->emptyConfiguration($result['error'] ?: 'Windows network configuration could not be read.', true,
                ! empty($result['timed_out']) ? 'process_timed_out' : 'process_failed');
        }

        $decoded = json_decode(trim($result['output']), true);
        if (!is_array($decoded)) {
            return $this->emptyConfiguration('Windows returned an unreadable network configuration response.');
        }

        $dns = $decoded['dnsServers'] ?? [];
        if (is_string($dns)) $dns = [$dns];
        $dns = array_values(array_unique(array_filter((array) $dns)));

        if (filled($decoded['error'] ?? null)) {
            $configuration = $this->emptyConfiguration((string) $decoded['error']);
            $configuration['interface_name'] = $decoded['interfaceName'] ?? $interfaceName;
            $configuration['error_code'] = $decoded['errorCode'] ?? 'configuration_read_failed';
            $configuration['windows_adapter_status'] = $decoded['windowsAdapterStatus'] ?? null;
            return $configuration;
        }
        if (filled($interfaceName) && ($decoded['interfaceName'] ?? null) !== $interfaceName) {
            $configuration = $this->emptyConfiguration('Windows did not return the requested adapter '.$interfaceName.'. A reading from a different adapter cannot verify this repair.');
            $configuration['interface_name'] = $interfaceName;
            return $configuration;
        }

        $state = $this->adapterState($decoded['adapterState'] ?? null);
        if (! is_bool($decoded['adapterAvailable'] ?? null) || $state === null
            || ($state !== 'missing' && ! is_string($decoded['interfaceName'] ?? null))
            || ($state !== 'missing' && trim($decoded['interfaceName']) === '')
            || $decoded['adapterAvailable'] !== ($state === 'up')) {
            return $this->emptyConfiguration('Windows returned an incomplete or inconsistent network configuration response.', true, 'configuration_invalid_response');
        }

        return [
            'supported' => true,
            'adapter_available' => (bool) ($decoded['adapterAvailable'] ?? false),
            'adapter_state' => $state,
            'interface_name' => $decoded['interfaceName'] ?? null,
            'local_ip' => $decoded['localIp'] ?? null,
            'gateway_ip' => $decoded['gatewayIp'] ?? null,
            'dns_servers' => $dns,
            'address_source' => $this->addressSource($decoded['addressSource'] ?? null),
            'dns_source' => $this->addressSource($decoded['dnsSource'] ?? null),
            'reader' => is_string($decoded['reader'] ?? null) ? $decoded['reader'] : null,
            'error' => null,
            'error_code' => null,
            'windows_adapter_status' => $decoded['windowsAdapterStatus'] ?? null,
        ];
    }

    public function enableAdapter(string $name): array
    {
        // Use the command that completed in this laptop's isolated Windows test.
        // A timeout remains unconfirmed; only fresh measurements verify recovery.
        return $this->changeAdapter($this->adapterName($name), 'Enable-NetAdapter');
    }

    public function restartAdapter(string $name): array
    {
        return $this->changeAdapter($this->adapterName($name), 'Restart-NetAdapter');
    }

    public function probeGateway(?string $gatewayIp): array
    {
        if (!$gatewayIp || filter_var($gatewayIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return ['reachable' => false, 'latency_ms' => null, 'error' => 'No valid IPv4 gateway is available.'];
        }
        if (!$this->isSupportedPlatform()) {
            return ['reachable' => false, 'latency_ms' => null, 'error' => 'Gateway probing requires Windows.'];
        }

        $safeIp = str_replace("'", '', $gatewayIp);
        $pingMs = max(250, min(1500, (int) ((float) config('netrescue.timeouts.ping_seconds', 5) * 1000 / 3)));
        $script = <<<POWERSHELL
\$ping = [System.Net.NetworkInformation.Ping]::new()
\$times = @()
try {
    1..2 | ForEach-Object {
        \$reply = \$ping.Send([System.Net.IPAddress]::Parse('{$safeIp}'), {$pingMs})
        if (\$reply.Status -eq [System.Net.NetworkInformation.IPStatus]::Success) { \$times += [double]\$reply.RoundtripTime }
    }
} finally { \$ping.Dispose() }
\$average = if (\$times.Count) { [Math]::Round((\$times | Measure-Object -Average).Average, 2) } else { \$null }
[pscustomobject]@{ reachable = (\$times.Count -gt 0); latencyMs = \$average } | ConvertTo-Json -Compress
POWERSHELL;

        $result = $this->runPowerShell($script, (float) config('netrescue.timeouts.ping_seconds', 5));
        $decoded = $result['successful'] ? json_decode(trim($result['output']), true) : null;
        $measured = is_array($decoded) && is_bool($decoded['reachable'] ?? null);

        return [
            'measured' => $measured,
            'reachable' => $measured && $decoded['reachable'],
            'latency_ms' => $measured && isset($decoded['latencyMs']) ? (float) $decoded['latencyMs'] : null,
            'error' => $measured ? null : ($result['error'] ?: 'Windows did not return a completed gateway ping result.'),
        ];
    }

    public function probeInternet(array $targets): array
    {
        $timeout = (float) config('netrescue.timeouts.socket_seconds', 2.5);
        $errors = [];

        foreach ($targets as $target) {
            $parsed = $this->parseTarget((string) $target);
            if (!$parsed) { $errors[] = "Invalid target: {$target}"; continue; }

            [$host, $port] = $parsed;
            $started = microtime(true);
            $errno = 0; $errstr = '';

            try {
                $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
            } catch (Throwable $e) {
                $socket = false; $errstr = $e->getMessage();
            }

            $elapsed = round((microtime(true) - $started) * 1000, 2);
            if (is_resource($socket)) {
                fclose($socket);
                return ['reachable' => true, 'latency_ms' => $elapsed, 'target' => "{$host}:{$port}", 'error' => null];
            }

            $errors[] = "{$host}:{$port} - ".($errstr ?: "socket error {$errno}");
        }

        return [
            'reachable' => false, 'latency_ms' => null, 'target' => null,
            'error' => $errors ? implode(' | ', $errors) : 'No Internet test targets are configured.',
        ];
    }

    public function probeDns(string $domain): array
    {
        $domain = strtolower(trim($domain));
        if (!$this->isValidDomain($domain)) {
            return ['operational' => false, 'latency_ms' => null, 'addresses' => [], 'error' => 'The DNS test domain is invalid.'];
        }
        if (!$this->isSupportedPlatform()) {
            return ['operational' => false, 'latency_ms' => null, 'addresses' => [], 'error' => 'DNS probing requires Windows.'];
        }

        $safeDomain = str_replace("'", '', $domain);
        $script = <<<POWERSHELL
\$watch = [System.Diagnostics.Stopwatch]::StartNew()
\$records = @(Resolve-DnsName -Name '{$safeDomain}' -Type A -DnsOnly -QuickTimeout -ErrorAction SilentlyContinue | Where-Object { \$_.IPAddress })
\$watch.Stop()
\$addresses = @(\$records | Select-Object -ExpandProperty IPAddress -Unique)
[pscustomobject]@{
    operational = (\$addresses.Count -gt 0)
    addresses = \$addresses
    latencyMs = [Math]::Round(\$watch.Elapsed.TotalMilliseconds, 2)
} | ConvertTo-Json -Compress -Depth 3
POWERSHELL;

        $result = $this->runPowerShell($script, (float) config('netrescue.timeouts.dns_seconds', 6));
        $decoded = $result['successful'] ? json_decode(trim($result['output']), true) : null;
        $addresses = $decoded['addresses'] ?? [];
        if (is_string($addresses)) $addresses = [$addresses];

        $addresses = array_values(array_unique(array_filter((array) $addresses,
            fn ($address) => is_string($address) && filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)));
        $measured = is_array($decoded) && is_bool($decoded['operational'] ?? null)
            && $decoded['operational'] === (count($addresses) > 0);
        $operational = $measured && $decoded['operational'];
        return [
            'measured' => $measured,
            'operational' => $operational,
            'latency_ms' => $operational ? (float) ($decoded['latencyMs'] ?? 0) : null,
            'addresses' => $addresses,
            'error' => ! $measured ? ($result['error'] ?: 'Windows did not return a completed DNS result.')
                : ($operational ? null : 'Windows returned no A records for '.$domain.'.'),
        ];
    }

    private function emptyConfiguration(string $error, bool $supported = true, string $errorCode = 'configuration_read_failed'): array
    {
        return [
            'supported' => $supported,
            'adapter_available' => false,
            'adapter_state' => null,
            'interface_name' => null,
            'local_ip' => null,
            'gateway_ip' => null,
            'dns_servers' => [],
            'address_source' => 'unknown',
            'dns_source' => 'unknown',
            'error' => $error,
            'error_code' => $errorCode,
            'windows_adapter_status' => null,
        ];
    }

    private function adapterState(mixed $state): ?string
    {
        $state = is_string($state) ? $state : null;

        return in_array($state, ['up', 'disabled', 'disconnected', 'missing'], true) ? $state : null;
    }

    private function addressSource(mixed $source): string
    {
        $source = is_string($source) ? $source : null;

        return in_array($source, ['dhcp', 'static'], true) ? $source : 'unknown';
    }

    private function adapterName(string $name): string
    {
        $name = trim($name);
        if (! preg_match('/^[A-Za-z0-9 ._()\\-]{1,64}$/', $name)) {
            throw new \RuntimeException('The adapter name is not valid.');
        }

        return $name;
    }

    private function changeAdapter(string $name, string $command): array
    {
        $script = resource_path('powershell/change-adapter.ps1');
        if (! is_readable($script)) {
            return ['successful' => false, 'output' => '', 'error' => 'The Windows adapter command script is missing.', 'exit_code' => null, 'timed_out' => false];
        }
        $action = $command === 'Enable-NetAdapter' ? 'Enable' : 'Restart';
        $actionCode = $action === 'Enable' ? 'enable_adapter' : 'restart_adapter';
        $result = $this->runProcess([$this->windowsTool('WindowsPowerShell\\v1.0\\powershell.exe'),
            '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $script,
            '-Action', $action, '-InterfaceName', $name], app(NetworkRequestTimeout::class)->commandSeconds($actionCode));

        return [
            'successful' => $result['successful'],
            'output' => $result['output'],
            'error' => $result['successful'] ? '' : ($result['error'] ?: 'Windows could not change the adapter.'),
            'exit_code' => $result['exit_code'] ?? null,
            'timed_out' => (bool) ($result['timed_out'] ?? false),
        ];
    }

    protected function runPowerShell(string $script, float $timeout): array
    {
        // A consistent encoding avoids cmd.exe quoting problems. Suppressing
        // progress keeps module-loading messages off the error stream.
        $script = "\$ProgressPreference = 'SilentlyContinue';\n".$script;
        $encoded = base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8'));
        return $this->runProcess([$this->windowsTool('WindowsPowerShell\\v1.0\\powershell.exe'),
            '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-EncodedCommand', $encoded], $timeout);
    }

    protected function runProcess(array $command, float $timeout): array
    {
        $process = null;
        try {
            if (!is_file($command[0])) {
                return ['successful' => false, 'output' => '', 'error' => 'Windows tool was not found: '.$command[0], 'exit_code' => null];
            }
            $systemRoot = dirname($this->windowsTool('cmd.exe'), 2);
            $process = new Process($command, null,
                ['SystemRoot' => $systemRoot, 'WINDIR' => $systemRoot, 'ComSpec' => $this->windowsTool('cmd.exe')]);
            $process->setTimeout($timeout);
            $process->run();
            $successful = $process->isSuccessful();
            $output = $this->normalizeProcessText($process->getOutput());
            $error = $this->normalizeProcessText($process->getErrorOutput());
            return [
                'successful' => $successful, 'output' => $output,
                'error' => $successful ? '' : ($error ?: 'Windows returned exit code '.$process->getExitCode().'. See command output.'),
                'exit_code' => $process->getExitCode(),
            ];
        } catch (Throwable $e) {
            return ['successful' => false,
                'output' => $process?->isStarted() ? $this->normalizeProcessText($process->getOutput()) : '',
                'error' => $e->getMessage(), 'exit_code' => null, 'timed_out' => $e instanceof ProcessTimedOutException];
        }
    }

    private function windowsTool(string $relative): string
    {
        $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR')
            ?: ($_SERVER['SystemRoot'] ?? null) ?: ($_SERVER['WINDIR'] ?? null) ?: 'C:\\Windows';
        return rtrim($systemRoot, '\\/').'\\System32\\'.$relative;
    }

    private function normalizeProcessText(string $value): string
    {
        if ($value === '') return '';

        if (str_contains($value, "\0")) {
            if (function_exists('mb_convert_encoding')) {
                try {
                    $converted = mb_convert_encoding($value, 'UTF-8', 'UTF-16LE');
                    $value = is_string($converted) && trim($converted) !== '' ? $converted : str_replace("\0", '', $value);
                } catch (Throwable) {
                    $value = str_replace("\0", '', $value);
                }
            } else {
                $value = str_replace("\0", '', $value);
            }
        }

        return trim(preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value);
    }

    private function parseTarget(string $target): ?array
    {
        $parts = explode(':', trim($target));
        if (count($parts) !== 2) return null;
        [$host, $port] = $parts; $port = (int) $port;
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || $port < 1 || $port > 65535) return null;
        return [$host, $port];
    }

    private function isValidDomain(string $domain): bool
    {
        return strlen($domain) <= 253
            && (bool) preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $domain);
    }
}
