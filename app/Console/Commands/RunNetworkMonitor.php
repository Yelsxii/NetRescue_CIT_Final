<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Models\HealthCheck;
use App\Models\MonitorSetting;
use App\Services\Network\NetworkHealthService;
use Illuminate\Console\Command;

class RunNetworkMonitor extends Command
{
    protected $signature = 'netrescue:monitor {--force : Run even if the configured interval has not elapsed}';

    protected $description = 'Run the automated NetRescue health monitor when it is due.';

    public function handle(NetworkHealthService $healthService): int
    {
        $settings = MonitorSetting::current();

        if (!$settings->enabled && !$this->option('force')) {
            $this->line('Monitoring is disabled.');
            return self::SUCCESS;
        }

        $localDevice = Device::localHost();

        $lastAutomatic = HealthCheck::query()
            ->where('device_id', $localDevice->id)
            ->where('source', 'automatic')
            ->latest('checked_at')->orderByDesc('id')
            ->first();

        if (! $settings->isDue($lastAutomatic?->checked_at) && ! $this->option('force')) {
            $next = $settings->nextAutomaticAt($lastAutomatic?->checked_at)->format('g:i A');
            $this->line('Not due yet. Automatic checks follow the '.$settings->interval_minutes.' minute setting. Next check '.$next.'.');

            return self::SUCCESS;
        }

        $result = $healthService->run('automatic', true);
        $check = $result['check'];

        $this->info(sprintf(
            'Health check #%d completed: %d%% %s - %s',
            $check->id,
            $check->health_score,
            strtoupper($check->health_status),
            $check->diagnosis_title,
        ));

        return self::SUCCESS;
    }
}
