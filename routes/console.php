<?php

use App\Console\Commands\MarkOfflineDevices;
use App\Console\Commands\RunNetworkMonitor;
use Illuminate\Support\Facades\Schedule;

Schedule::command(RunNetworkMonitor::class)->everyMinute()->withoutOverlapping();
Schedule::command(MarkOfflineDevices::class)->everyMinute()->withoutOverlapping();
