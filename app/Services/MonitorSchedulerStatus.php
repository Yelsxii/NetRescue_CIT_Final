<?php

namespace App\Services;

use App\Models\MonitorSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class MonitorSchedulerStatus
{
    private const CACHE_KEY = 'netrescue:scheduler:last-tick';

    // The scheduler ticks every minute. Allow a slow check and normal startup
    // delay before treating its last observed tick as stale.
    private const MAX_TICK_AGE_SECONDS = 150;

    public function heartbeat(): void
    {
        Cache::put(self::CACHE_KEY, now()->getTimestamp(), now()->addDay());
    }

    public function status(MonitorSetting $settings): array
    {
        $lastTick = $this->lastTick();
        $age = $lastTick?->diffInSeconds(now());
        $ready = $age !== null && $age >= 0 && $age <= self::MAX_TICK_AGE_SECONDS;

        return [
            'state' => ! $settings->enabled ? 'paused' : ($ready ? 'ready' : 'scheduler_unconfirmed'),
            'scheduler_ready' => $ready,
            'last_tick_at' => $lastTick?->toIso8601String(),
            'label' => ! $settings->enabled ? 'Paused' : ($ready ? 'Every '.$settings->interval_minutes.' min' : 'Scheduler not detected'),
            'message' => ! $settings->enabled
                ? 'Automatic checks are paused in Settings.'
                : ($ready
                    ? 'The scheduler is responding. Automatic checks follow the selected interval.'
                    : 'Automatic checks are enabled, but no recent scheduler tick has been detected.'),
        ];
    }

    private function lastTick(): ?Carbon
    {
        try {
            $timestamp = Cache::get(self::CACHE_KEY);

            return is_int($timestamp) && $timestamp > 0
                ? Carbon::createFromTimestamp($timestamp, config('app.timezone'))
                : null;
        } catch (Throwable) {
            // A cache-read failure must not prevent viewing existing results.
            return null;
        }
    }
}
