<?php

namespace App\Console\Commands;

use App\Models\Device;
use App\Services\Network\IncidentManager;
use Illuminate\Console\Command;

class MarkOfflineDevices extends Command
{
    protected $signature = 'netrescue:mark-offline';
    protected $description = 'Mark remote agents offline when their heartbeat becomes stale.';

    public function handle(IncidentManager $incidentManager): int
    {
        $cutoff = now()->subMinutes((int) config('netrescue.agent.offline_after_minutes', 3));

        Device::query()
            ->where('is_local', false)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '<', $cutoff)
            ->get()
            ->each(function (Device $device) use ($incidentManager) {
                if ($device->status !== 'offline') {
                    $device->update(['status' => 'offline']);
                }

                $incidentManager->createOfflineIncident($device);
            });

        return self::SUCCESS;
    }
}
