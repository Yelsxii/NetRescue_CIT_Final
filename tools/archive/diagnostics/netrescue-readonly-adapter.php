<?php

declare(strict_types=1);

// Temporary localhost diagnostic. No health/incident/repair services are called.
// The only adapter-change cmdlet invocation below includes the literal -WhatIf.
if (PHP_SAPI === 'cli') {
    exit("Copy this file into NetRescue's public folder, then open http://127.0.0.1:8000/netrescue-readonly-adapter.php\n");
}
if (! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) {
    http_response_code(403);
    exit('This diagnostic is available only on this computer.');
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit('Open the diagnostic URL normally.');
}
header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");
header('Referrer-Policy: no-referrer');

function nrReadOnlyPrint(array $data): void
{
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";
    if (ob_get_level()) @ob_flush();
    flush();
}

if (PHP_OS_FAMILY !== 'Windows') {
    nrReadOnlyPrint(['diagnostic' => 'stopped', 'reason' => 'Run this through NetRescue on the Windows laptop. No adapter command was run.']);
    exit;
}
$projectRoot = dirname(__DIR__);
if (! is_file($projectRoot.'/vendor/autoload.php') || ! is_file($projectRoot.'/resources/powershell/read-configuration.ps1')) {
    nrReadOnlyPrint(['diagnostic' => 'stopped', 'reason' => 'Place this file inside C:\\xampp\\htdocs\\NetRescue_CIT_Final\\public.']);
    exit;
}

$temporary = null;
try {
    require $projectRoot.'/vendor/autoload.php';
    $limit = (int) ini_get('max_execution_time');
    if ($limit !== 0 && $limit < 120 && (! function_exists('set_time_limit') || ! @set_time_limit(120))) {
        throw new RuntimeException('PHP could not allow enough time for the diagnostic. No adapter command was run.');
    }
    ignore_user_abort(true);
    $systemRoot = getenv('SystemRoot') ?: getenv('WINDIR') ?: ($_SERVER['SystemRoot'] ?? 'C:\\Windows');
    $powerShell = rtrim($systemRoot, '\\/').'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
    if (! is_file($powerShell)) throw new RuntimeException('Windows PowerShell was not found.');
    $environment = ['SystemRoot' => $systemRoot, 'WINDIR' => $systemRoot, 'ComSpec' => rtrim($systemRoot, '\\/').'\\System32\\cmd.exe'];
    nrReadOnlyPrint([
        'context' => PHP_SAPI, 'php_version' => PHP_VERSION, 'php_bits' => PHP_INT_SIZE * 8,
        'adapter' => 'Wi-Fi',
        'scope' => 'Read-only: local snapshots, module/query timing, and Enable-NetAdapter -WhatIf simulation. No real enable/disable/restart, Internet probe or database write.',
        'files_sha256' => [
            'NetworkProbeService.php' => hash_file('sha256', $projectRoot.'/app/Services/Network/NetworkProbeService.php'),
            'read-configuration.ps1' => hash_file('sha256', $projectRoot.'/resources/powershell/read-configuration.ps1'),
        ],
    ]);

    $scripts = [
        'snapshot' => [8, <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$identity = [Security.Principal.WindowsIdentity]::GetCurrent()
$principal = New-Object Security.Principal.WindowsPrincipal($identity)
$found = @([System.Net.NetworkInformation.NetworkInterface]::GetAllNetworkInterfaces() | Where-Object { $_.Name -eq 'Wi-Fi' })
$reading = @($found | ForEach-Object {
    $p = $_.GetIPProperties()
    [pscustomobject]@{
        name = $_.Name
        operationalStatus = [string]$_.OperationalStatus
        ipv4 = @($p.UnicastAddresses | Where-Object { $_.Address.AddressFamily -eq [System.Net.Sockets.AddressFamily]::InterNetwork } | ForEach-Object { $_.Address.ToString() })
        gateways = @($p.GatewayAddresses | ForEach-Object { $_.Address.ToString() })
    }
})
[pscustomobject]@{
    administrator = $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
    child64bit = [Environment]::Is64BitProcess
    powershellVersion = $PSVersionTable.PSVersion.ToString()
    target = $reading
} | ConvertTo-Json -Compress -Depth 5
POWERSHELL],
        'module_query_and_whatif' => [35, <<<'POWERSHELL'
$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$watch = [System.Diagnostics.Stopwatch]::StartNew()
function Write-Stage([string]$Name, $Data = $null) {
    $line = [pscustomobject]@{ stage = $Name; elapsed_ms = $watch.ElapsedMilliseconds; data = $Data } | ConvertTo-Json -Compress -Depth 5
    [Console]::Out.WriteLine($line)
    [Console]::Out.Flush()
}
Write-Stage 'powershell_started'
try {
    Write-Stage 'module_import_start'
    Import-Module NetAdapter -ErrorAction Stop
    $cmd = Get-Command Enable-NetAdapter -ErrorAction Stop
    Write-Stage 'module_import_finished' @{ module = $cmd.ModuleName; type = [string]$cmd.CommandType }

    Write-Stage 'get_adapter_start'
    $adapters = @(Get-NetAdapter -Name 'Wi-Fi' -ErrorAction Stop | Where-Object { $_.Name -eq 'Wi-Fi' })
    if ($adapters.Count -ne 1) { throw 'Windows did not return exactly one Wi-Fi adapter.' }
    $a = $adapters[0]
    Write-Stage 'get_adapter_finished' @{
        name = [string]$a.Name
        status = [string]$a.Status
        adminStatus = [string]$a.AdminStatus
        mediaConnectionState = [string]$a.MediaConnectionState
        interfaceOperationalStatus = [string]$a.InterfaceOperationalStatus
        driverDescription = [string]$a.DriverDescription
        driverVersion = [string]$a.DriverVersionString
    }

    Write-Stage 'whatif_start'
    Enable-NetAdapter -Name 'Wi-Fi' -WhatIf -Confirm:$false -ErrorAction Stop
    Write-Stage 'whatif_finished'

    Write-Stage 'windows_device_status_start'
    $devices = @(Get-CimInstance -ClassName Win32_NetworkAdapter -Filter "NetConnectionID='Wi-Fi'" -OperationTimeoutSec 8 -ErrorAction Stop | Select-Object NetConnectionID, NetEnabled, NetConnectionStatus, ConfigManagerErrorCode, Status)
    Write-Stage 'windows_device_status_finished' $devices
    Write-Stage 'finished'
} catch {
    Write-Stage 'error' @{ message = $_.Exception.Message }
    exit 1
}
POWERSHELL],
    ];

    // A short -File invocation avoids encoded cmd.exe command-line length limits.
    foreach ($scripts as $phase => [$timeout, $script]) {
        $temporary = tempnam(sys_get_temp_dir(), 'nr-ro-');
        if ($temporary === false) { $temporary = null; throw new RuntimeException('PHP could not create a temporary diagnostic script.'); }
        $scriptFile = $temporary.'.ps1';
        if (! @rename($temporary, $scriptFile)) throw new RuntimeException('PHP could not prepare the temporary script name.');
        $temporary = $scriptFile;
        if (file_put_contents($temporary, $script) === false) throw new RuntimeException('PHP could not write the temporary diagnostic script.');
        nrReadOnlyRun($phase, [$powerShell, '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $temporary], $timeout, $environment);
        @unlink($temporary);
        $temporary = null;
    }
    nrReadOnlyRun('installed_target_reader', [$powerShell, '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-File', $projectRoot.'/resources/powershell/read-configuration.ps1', '-InterfaceName', 'Wi-Fi'], 20, $environment);
    nrReadOnlyPrint(['diagnostic' => 'finished', 'next_step' => 'Copy all output and send it. Keep Wi-Fi enabled; do not repeat the disabled-adapter test yet. Delete this temporary PHP diagnostic after collecting the output.']);
} catch (Throwable $e) {
    nrReadOnlyPrint(['diagnostic' => 'stopped', 'error' => $e->getMessage()]);
} finally {
    if (is_string($temporary) && is_file($temporary)) @unlink($temporary);
}

function nrReadOnlyRun(string $phase, array $command, float $timeout, array $environment): void
{
    nrReadOnlyPrint(['starting' => $phase, 'timeout_seconds' => $timeout]);
    $started = microtime(true);
    $process = new Symfony\Component\Process\Process($command, null, $environment);
    $process->setTimeout($timeout);
    $error = null;
    $timedOut = false;
    try {
        // Stream stage output even if a later operation hangs.
        $process->run(function (string $type, string $buffer) use ($phase): void {
            nrReadOnlyPrint(['phase' => $phase, 'stream' => $type, 'text' => $buffer]);
        });
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $timedOut = $e instanceof Symfony\Component\Process\Exception\ProcessTimedOutException;
    }
    nrReadOnlyPrint([
        'phase' => $phase, 'seconds' => round(microtime(true) - $started, 2),
        'timed_out' => $timedOut, 'exit_code' => $timedOut ? null : $process->getExitCode(),
        'error' => $error,
    ]);
}
