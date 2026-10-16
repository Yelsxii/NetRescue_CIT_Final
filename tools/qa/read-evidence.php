<?php

// CLI-only readout for the Windows QA runner. Never expose this through public/.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$host = \App\Models\Device::localHost();
$settings = \App\Models\MonitorSetting::current();
$check = $host->healthChecks()->first();
$incident = \App\Models\Incident::where('device_id', $host->id)->where('status', 'active')->latest('last_detected_at')->orderByDesc('id')->first();
$action = \App\Models\RecoveryAction::where('device_id', $host->id)->latest('started_at')->orderByDesc('id')->first();

echo json_encode([
    'captured_at' => now()->toIso8601String(),
    'settings' => ['monitoring_enabled' => $settings->enabled, 'internet_targets' => $settings->internet_targets ?: config('netrescue.internet_targets'),
        'dns_test_domain' => $settings->dns_test_domain, 'latency_warning_ms' => $settings->latency_warning_ms],
    'check' => $check?->toArray(),
    'active_incident' => $incident ? $incident->toArray() + ['can_repair' => $incident->canRepair(), 'next_step' => $incident->nextStep()] : null,
    'latest_recovery' => $action ? $action->toArray() + ['outcome_summary' => $action->outcomeSummary()] : null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
