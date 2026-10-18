<?php

declare(strict_types=1);

// Temporary local diagnostic. Uses the installed NetRescue service unchanged.
// Never disables an adapter and never writes incident or repair records.
if (PHP_SAPI === 'cli') {
    exit("Copy this file into NetRescue's public directory and open it through http://127.0.0.1:8000.\n");
}
if (! in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('This diagnostic is available only on the local computer.');
}
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
header('Referrer-Policy: no-referrer');

session_name('netrescue_adapter_diagnostic');
if (! @session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict', 'use_strict_mode' => true])) {
    http_response_code(503);
    exit('PHP could not open its session directory. No adapter command was run. Check session.save_path in the serving PHP configuration.');
}
$_SESSION['token'] ??= bin2hex(random_bytes(32));
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'GET') {
    header('Content-Type: text/html; charset=UTF-8');
    $token = htmlspecialchars($_SESSION['token'], ENT_QUOTES, 'UTF-8');
    session_write_close();
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>NetRescue adapter diagnostic</title><main><h1>Isolated Wi-Fi repair diagnostic</h1><p>This uses your installed NetRescue configuration reader and enable command. It reports each phase separately.</p><ol><li>Keep automatic dashboard checks paused and Laravel running as Administrator.</li><li>In a separate Administrator PowerShell window, disable Wi-Fi once: <code>Disable-NetAdapter -Name "Wi-Fi" -Confirm:$false -ErrorAction Stop</code></li><li>Return here and press the button once. Do not press Run repair on the dashboard at the same time.</li></ol><p>The test enables only an adapter measured as disabled and named exactly <strong>Wi-Fi</strong>. It does not disable adapters or create, close, or verify incident records.</p><form method="post"><input type="hidden" name="token" value="'.$token.'"><button type="submit">Run isolated Wi-Fi repair test</button></form><p>If Wi-Fi stays disabled, restore it in Administrator PowerShell: <code>Enable-NetAdapter -Name "Wi-Fi" -Confirm:$false -ErrorAction Stop</code></p><p>After collecting the output, delete this temporary PHP file from public.</p></main></html>';
    exit;
}
if ($method !== 'POST') {
    session_write_close();
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Use the test page button.');
}
if (! is_string($_POST['token'] ?? null) || ! hash_equals($_SESSION['token'], $_POST['token'])) {
    session_write_close();
    http_response_code(403);
    exit('Open the diagnostic page again and use its button.');
}
// Consume the form token so refresh/resubmission cannot repeat the command.
$_SESSION['token'] = bin2hex(random_bytes(32));
session_write_close();
header('Content-Type: text/plain; charset=UTF-8');

function adapterDiagnosticPrint(array $value): void
{
    echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";
    if (ob_get_level()) {
        @ob_flush();
    }
    flush();
}

if (PHP_OS_FAMILY !== 'Windows') {
    adapterDiagnosticPrint(['diagnostic' => 'stopped', 'reason' => 'Run this on the Windows laptop. No adapter command was run.']);
    exit;
}
$projectRoot = dirname(__DIR__);
if (! is_file($projectRoot.'/vendor/autoload.php')) {
    adapterDiagnosticPrint(['diagnostic' => 'stopped', 'reason' => "Copy this file into C:\\xampp\\htdocs\\NetRescue_CIT_Final\\public. No adapter command was run."]);
    exit;
}

$phase = 'bootstrap';
try {
    require $projectRoot.'/vendor/autoload.php';
    $app = require $projectRoot.'/bootstrap/app.php';
    $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
    $readTimeout = (float) config('netrescue.timeouts.powershell_seconds', 20);
    $enableTimeout = (float) config('netrescue.timeouts.recovery_seconds', 35);
    if (! is_finite($readTimeout) || ! is_finite($enableTimeout) || $readTimeout <= 0 || $enableTimeout <= 0) {
        throw new RuntimeException('Configuration and recovery timeouts must be positive finite values.');
    }
    $budget = max(120, (int) ceil(2 * $readTimeout + $enableTimeout + 15));
    $currentLimit = (int) ini_get('max_execution_time');
    if ($currentLimit !== 0 && $currentLimit < $budget
        && (! function_exists('set_time_limit') || ! @set_time_limit($budget))) {
        throw new RuntimeException('PHP execution limit is too short for this diagnostic. No adapter command was run.');
    }
    $probe = $app->make(App\Services\Network\NetworkProbeService::class);
    adapterDiagnosticPrint([
        'context' => PHP_SAPI,
        'service' => get_class($probe),
        'configuration_timeout_seconds' => $readTimeout,
        'enable_timeout_seconds' => $enableTimeout,
        'note' => 'Using the installed service unchanged. No incident or repair records are written.',
    ]);

    $phase = 'before_configuration';
    adapterDiagnosticPrint(['starting' => $phase]);
    $started = microtime(true);
    $before = $probe->collectConfiguration();
    adapterDiagnosticPrint(['phase' => $phase, 'seconds' => round(microtime(true) - $started, 2), 'configuration' => $before]);
    if (! ($before['supported'] ?? false) || ! empty($before['error'])
        || ($before['adapter_state'] ?? null) !== 'disabled' || ($before['interface_name'] ?? null) !== 'Wi-Fi') {
        adapterDiagnosticPrint([
            'diagnostic' => 'stopped_before_enable',
            'reason' => 'The installed reader did not report a disabled adapter named exactly Wi-Fi. No enable command was run.',
            'next_step' => 'Send this output. If Wi-Fi is disabled, restore it manually with Enable-NetAdapter.',
        ]);
        exit;
    }

    $phase = 'enable_adapter';
    adapterDiagnosticPrint(['starting' => $phase]);
    $started = microtime(true);
    $command = $probe->enableAdapter('Wi-Fi');
    adapterDiagnosticPrint(['phase' => $phase, 'seconds' => round(microtime(true) - $started, 2), 'command_result' => $command]);
    if ($command['successful'] ?? false) {
        usleep(2500000); // Match the installed repair flow's reconnect delay.
    }

    $phase = 'after_configuration';
    adapterDiagnosticPrint(['starting' => $phase]);
    $started = microtime(true);
    $after = $probe->collectConfiguration();
    adapterDiagnosticPrint(['phase' => $phase, 'seconds' => round(microtime(true) - $started, 2), 'configuration' => $after]);
    $observedUp = empty($after['error']) && ($after['supported'] ?? false)
        && ($after['interface_name'] ?? null) === 'Wi-Fi' && ($after['adapter_state'] ?? null) === 'up';
    adapterDiagnosticPrint([
        'diagnostic' => 'finished',
        'enable_command_reported_success' => (bool) ($command['successful'] ?? false),
        'wifi_up_in_followup_configuration' => $observedUp,
        'scope' => 'This isolates the enable command and configuration reader. Gateway, Internet, DNS, incident closure, and the complete dashboard repair flow were not tested.',
        'next_step' => 'Send the complete output. If Wi-Fi stayed disabled, restore it manually. Then run Central Check once to update the dashboard; do not resubmit this POST page.',
    ]);
} catch (Throwable $e) {
    adapterDiagnosticPrint([
        'diagnostic' => 'interrupted', 'phase' => $phase, 'exception' => get_class($e), 'message' => $e->getMessage(),
        'next_step' => 'Send this output. Restore Wi-Fi manually if needed. Do not resubmit this POST page.',
    ]);
}
