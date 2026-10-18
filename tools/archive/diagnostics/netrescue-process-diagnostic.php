<?php

declare(strict_types=1);

// Read-only diagnostic: copy into NetRescue's public directory.
if (PHP_SAPI !== 'cli' && ! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('This diagnostic is available only on the local computer.');
}
if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=UTF-8');
    header('Cache-Control: no-store');
}
if (function_exists('set_time_limit')) {
    set_time_limit(120);
}

$projectRoot = dirname(__DIR__);
if (! is_file($projectRoot.'/vendor/autoload.php')) {
    exit("Copy this file into C:\\xampp\\htdocs\\NetRescue_CIT_Final\\public first.\n");
}
require $projectRoot.'/vendor/autoload.php';
$app = require $projectRoot.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function diagnosticPrint(array $result): void
{
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";
    if (ob_get_level()) {
        @ob_flush();
    }
    flush();
}

if (PHP_OS_FAMILY !== 'Windows') {
    diagnosticPrint(['error' => 'Run this diagnostic on the Windows laptop.']);
    exit;
}

$systemRoot = getenv('SystemRoot') ?: getenv('WINDIR') ?: ($_SERVER['SystemRoot'] ?? null) ?: ($_SERVER['WINDIR'] ?? null) ?: 'C:\\Windows';
$systemRoot = rtrim($systemRoot, '\\/');
$powershell = $systemRoot.'\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
$environment = ['SystemRoot' => $systemRoot, 'WINDIR' => $systemRoot, 'ComSpec' => $systemRoot.'\\System32\\cmd.exe'];
$flags = [$powershell, '-NoLogo', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass'];
$sample = new Symfony\Component\Process\Process(array_merge($flags, ['-Command', 'exit 0']), null, $environment);
$envMethod = new ReflectionMethod($sample, 'getDefaultEnv');
$inherited = $envMethod->invoke($sample);
$environmentUnits = 1;
foreach ($inherited as $key => $value) {
    if (is_scalar($value) && $value !== false) {
        $environmentUnits += (int) (strlen(mb_convert_encoding($key.'='.$value."\0", 'UTF-16LE', 'UTF-8')) / 2);
    }
}
diagnosticPrint([
    'context' => PHP_SAPI,
    'php_version' => PHP_VERSION,
    'php_bits' => PHP_INT_SIZE * 8,
    'inherited_environment_utf16_units' => $environmentUnits,
    'note' => 'No environment values or credentials are printed. No network settings are changed.',
]);

$basicScript = <<<'PS'
Write-Output 'stage: powershell_started'
Write-Output ('child_64bit: ' + [Environment]::Is64BitProcess)
$principal = [Security.Principal.WindowsPrincipal]::new([Security.Principal.WindowsIdentity]::GetCurrent())
Write-Output ('administrator: ' + $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator))
Write-Output 'stage: finished'
exit 0
PS;
$networkScript = <<<'PS'
$ErrorActionPreference = 'Stop'
Write-Output 'stage: powershell_started'
$adapter = Get-NetAdapter -Name 'Wi-Fi' -ErrorAction Stop
Write-Output ('stage: adapter_read; status: ' + $adapter.Status)
$configuration = Get-NetIPConfiguration -InterfaceAlias 'Wi-Fi' -ErrorAction Stop
Write-Output ('stage: configuration_read; interface: ' + $configuration.InterfaceAlias)
Write-Output 'stage: finished'
exit 0
PS;

foreach ([['basic', $basicScript, 8], ['network_read', $networkScript, 20]] as [$name, $script, $timeout]) {
    foreach (['raw', 'encoded'] as $mode) {
        $argument = $mode === 'encoded' ? base64_encode(mb_convert_encoding($script, 'UTF-16LE', 'UTF-8')) : $script;
        $command = array_merge($flags, [$mode === 'encoded' ? '-EncodedCommand' : '-Command', $argument]);
        $process = new Symfony\Component\Process\Process($command, null, $environment);
        $process->setTimeout($timeout);
        diagnosticPrint(['starting' => $name.'_'.$mode, 'timeout_seconds' => $timeout]);
        $start = microtime(true);
        $error = null;
        $timedOut = false;
        try {
            $process->run();
        } catch (Symfony\Component\Process\Exception\ProcessTimedOutException $e) {
            $timedOut = true;
            $error = 'Process exceeded '.$timeout.' seconds.';
        } catch (Throwable $e) {
            $error = substr($e->getMessage(), -400);
        }
        $started = $process->isStarted();
        diagnosticPrint([
            'test' => $name.'_'.$mode,
            'seconds' => round(microtime(true) - $start, 2),
            'timed_out' => $timedOut,
            'process_started' => $started,
            'exit_code' => $timedOut || ! $started ? null : $process->getExitCode(),
            'output' => $started ? trim(str_replace("\0", '', $process->getOutput())) : '',
            'error' => $error ?: ($started ? trim(str_replace("\0", '', $process->getErrorOutput())) : 'Process did not start') ?: null,
        ]);
    }
}
diagnosticPrint(['diagnostic' => 'finished', 'next_step' => 'Send this output. Keep Wi-Fi enabled.']);
