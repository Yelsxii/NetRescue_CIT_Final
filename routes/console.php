<?php

use App\Console\Commands\RunNetworkMonitor;
use App\Services\MonitorSchedulerStatus;
use Illuminate\Support\Facades\Schedule;

// This tick is independent of the enabled setting and performs no network
// measurement. It confirms that the scheduler, rather than only the web
// server, has actually run recently.
Schedule::call(fn () => app(MonitorSchedulerStatus::class)->heartbeat())
    ->name('netrescue:scheduler-heartbeat')->everyMinute();

Schedule::command(RunNetworkMonitor::class)->everyMinute()->withoutOverlapping();
