<?php

namespace App\Services\Network;

use Symfony\Component\Process\Process;
use Throwable;

class NetworkProbeService
{
    public function isSupportedPlatform(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    public function collectConfiguration(): array
    {
        if (!$this->isSupportedPlatform()) {
            return $this->emptyConfiguration('NetRescue network probes require Windows 10 or Windows 11.', false);
        }

        $script = <<<'POWERSHELL'
function Write-Reading($available, $state, $name, $ip, $gateway, $dns, $addressSource, $dnsSource) {
    [pscustomobject]@{
        adapterAvailable = [bool]$available
        adapterState = $state
        interfaceName = $name
        localIp = $ip
        gatewayIp = $gateway
        dnsServers = @($dns)
        addressSource = $addressSource
        dnsSource = $dnsSource
    } | ConvertTo-Json -Compress -Depth 4
}

function Get-AddressSource([int]$interfaceIndex) {
    $ipif = Get-NetIPInterface -InterfaceIndex $interfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue
    if (-not $ipif) { return 'unknown' }
    if ([string]$ipif.Dhcp -eq 'Enabled') { return 'dhcp' }
    return 'static'
}

function Get-DnsSource($interfaceGuid) {
    if (-not $interfaceGuid) { return 'unknown' }
    $path = "HKLM:\SYSTEM\CurrentControlSet\Services\Tcpip\Parameters\Interfaces\$interfaceGuid"
    $reg = Get-ItemProperty -Path $path -ErrorAction SilentlyContinue
    if (-not $reg) { return 'unknown' }
    if ([string]$reg.NameServer -and [string]$reg.NameServer.Trim()) { return 'static' }
    return 'dhcp'
}

function Select-NamedAdapter { param($Adapters)
    $wifi = @($Adapters | Where-Object { $_.Name -match 'Wi-?Fi|Wireless|WLAN' } | Select-Object -First 1)
    if ($wifi) { return $wifi[0] }
    $ethernet = @($Adapters | Where-Object { $_.Name -match 'Ethernet' } | Select-Object -First 1)
    if ($ethernet) { return $ethernet[0] }
    return $null
}

try {
    $route = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -AddressFamily IPv4 -ErrorAction SilentlyContinue |
        Where-Object { $_.NextHop -and $_.NextHop -ne '0.0.0.0' } |
        Sort-Object RouteMetric, InterfaceMetric |
        Select-Object -First 1

    if ($route) {
        $cfg = Get-NetIPConfiguration -InterfaceIndex $route.InterfaceIndex -ErrorAction SilentlyContinue
    } else {
        $cfg = Get-NetIPConfiguration -ErrorAction SilentlyContinue |
            Where-Object { $_.NetAdapter.Status -eq 'Up' } |
            Select-Object -First 1
    }

    if ($cfg) {
        $ipv4 = @($cfg.IPv4Address | ForEach-Object { $_.IPAddress } | Where-Object { $_ -and $_ -notlike '127.*' }) | Select-Object -First 1
        $gateway = @($cfg.IPv4DefaultGateway | ForEach-Object { $_.NextHop } | Where-Object { $_ }) | Select-Object -First 1
        $dns = @($cfg.DNSServer.ServerAddresses | Where-Object { $_ } | Select-Object -Unique)
        $guid = $null
        $adapter = Get-NetAdapter -InterfaceIndex $cfg.InterfaceIndex -ErrorAction SilentlyContinue
        if ($adapter) { $guid = $adapter.InterfaceGuid }
        $addressSource = 'unknown'
        $dnsSource = 'unknown'
        try { $addressSource = Get-AddressSource ([int]$cfg.InterfaceIndex) } catch { $addressSource = 'unknown' }
        try { $dnsSource = Get-DnsSource $guid } catch { $dnsSource = 'unknown' }
        Write-Reading $true 'up' $cfg.InterfaceAlias $ipv4 $gateway $dns $addressSource $dnsSource
        exit 0
    }

    $physical = @(Get-NetAdapter -ErrorAction SilentlyContinue | Where-Object { $_.HardwareInterface -eq $true })
    $disabled = Select-NamedAdapter -Adapters @($physical | Where-Object { $_.Status -eq 'Disabled' })
    if ($disabled) {
        Write-Reading $false 'disabled' $disabled.Name $null $null @() 'unknown' 'unknown'
        exit 0
    }
    $disconnected = Select-NamedAdapter -Adapters @($physical | Where-Object { $_.Status -in @('Disconnected', 'Lower Layer Down') })
    if ($disconnected) {
        Write-Reading $false 'disconnected' $disconnected.Name $null $null @() 'unknown' 'unknown'
        exit 0
    }

    Write-Reading $false 'missing' $null $null $null @() 'unknown' 'unknown'
} catch {
    [pscustomobject]@{
        adapterAvailable = $false
        adapterState = $null
        interfaceName = $null
        localIp = $null
        gatewayIp = $null
        dnsServers = @()
        addressSource = 'unknown'
        dnsSource = 'unknown'
        error = $_.Exception.Message
    } | ConvertTo-Json -Compress -Depth 4
}
POWERSHELL;

        $result = $this->runPowerShell($script, (float) config('netrescue.timeouts.powershell_seconds', 20));

        if (!$result['successful']) {
            return $this->emptyConfiguration($result['error'] ?: 'Windows network configuration could not be read.');
        }

        $decoded = json_decode(trim($result['output']), true);
        if (!is_array($decoded)) {
            return $this->emptyConfiguration('Windows returned an unreadable network configuration response.');
        }

        $dns = $decoded['dnsServers'] ?? [];
        if (is_string($dns)) $dns = [$dns];
        $dns = array_values(array_unique(array_filter((array) $dns)));

        if (filled($decoded['error'] ?? null)) {
            return $this->emptyConfiguration((string) $decoded['error']);
        }

        return [
            'supported' => true,
            'adapter_available' => (bool) ($decoded['adapterAvailable'] ?? false),
            'adapter_state' => $this->adapterState($decoded['adapterState'] ?? null),
            'interface_name' => $decoded['interfaceName'] ?? null,
            'local_ip' => $decoded['localIp'] ?? null,
            'gateway_ip' => $decoded['gatewayIp'] ?? null,
            'dns_servers' => $dns,
            'address_source' => $this->addressSource($decoded['addressSource'] ?? null),
            'dns_source' => $this->addressSource($decoded['dnsSource'] ?? null),
            'error' => null,
        ];
    }

    public function enableAdapter(string $name): array
    {
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
        $script = <<<POWERSHELL
\$items = @(Test-Connection -ComputerName '{$safeIp}' -Count 2 -ErrorAction SilentlyContinue)
\$times = @(\$items | Where-Object { \$_.StatusCode -eq 0 } | ForEach-Object { [double]\$_.ResponseTime })
if (\$times.Count -gt 0) {
    \$avg = [Math]::Round((\$times | Measure-Object -Average).Average, 2)
    [pscustomobject]@{ reachable = \$true; latencyMs = \$avg } | ConvertTo-Json -Compress
} else {
    [pscustomobject]@{ reachable = \$false; latencyMs = \$null } | ConvertTo-Json -Compress
}
POWERSHELL;

        $result = $this->runPowerShell($script, (float) config('netrescue.timeouts.ping_seconds', 5));
        $decoded = $result['successful'] ? json_decode(trim($result['output']), true) : null;

        return [
            'reachable' => (bool) ($decoded['reachable'] ?? false),
            'latency_ms' => isset($decoded['latencyMs']) ? (float) $decoded['latencyMs'] : null,
            'error' => $result['successful'] ? null : ($result['error'] ?: 'Gateway reachability test failed.'),
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
\$records = @(Resolve-DnsName -Name '{$safeDomain}' -Type A -DnsOnly -ErrorAction SilentlyContinue | Where-Object { \$_.IPAddress })
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

        $operational = (bool) ($decoded['operational'] ?? false);
        return [
            'operational' => $operational,
            'latency_ms' => $operational ? (float) ($decoded['latencyMs'] ?? 0) : null,
            'addresses' => array_values(array_unique(array_filter((array) $addresses))),
            'error' => $result['successful'] ? null : ($result['error'] ?: 'DNS resolution test failed.'),
        ];
    }

    private function emptyConfiguration(string $error, bool $supported = true): array
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
        $script = "\$ErrorActionPreference = 'Stop'; ".$command." -Name '".$name."' -Confirm:\$false -ErrorAction Stop";
        $result = $this->runPowerShell($script, (float) config('netrescue.timeouts.recovery_seconds', 35));

        return [
            'successful' => $result['successful'],
            'output' => $result['output'],
            'error' => $result['successful'] ? '' : ($result['error'] ?: 'Windows could not change the adapter.'),
            'exit_code' => $result['exit_code'] ?? null,
        ];
    }

    private function runPowerShell(string $script, float $timeout): array
    {
        try {
            $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR')
                ?: ($_SERVER['SystemRoot'] ?? null) ?: ($_SERVER['WINDIR'] ?? null) ?: 'C:\\Windows';
            $systemRoot = rtrim($systemRoot, '\\/');
            $powershell = $systemRoot.'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';

            if (!is_file($powershell)) {
                return ['successful' => false, 'output' => '', 'error' => "Windows PowerShell was not found at {$powershell}.", 'exit_code' => null];
            }

            $process = new Process(
                [$powershell, '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $script],
                null,
                ['SystemRoot' => $systemRoot, 'WINDIR' => $systemRoot, 'ComSpec' => $systemRoot.'\\System32\\cmd.exe']
            );
            $process->setTimeout($timeout);
            $process->run();

            return [
                'successful' => $process->isSuccessful(),
                'output' => $this->normalizeProcessText($process->getOutput()),
                'error' => $this->normalizeProcessText($process->getErrorOutput()),
                'exit_code' => $process->getExitCode(),
            ];
        } catch (Throwable $e) {
            return ['successful' => false, 'output' => '', 'error' => $e->getMessage(), 'exit_code' => null];
        }
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
