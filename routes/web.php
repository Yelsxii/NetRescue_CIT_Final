<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MonitorSettingController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| CIT Administrator Console
|--------------------------------------------------------------------------
| NetRescue is an administrator-only utility. The browser dashboard is used
| only on the Central CIT computer to monitor the network, devices, and
| automatically generated incidents.
*/
Route::middleware('central.admin')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/devices', [DeviceController::class, 'index'])->name('devices.index');
    Route::get('/devices/{device}', [DeviceController::class, 'show'])->name('devices.show');
    Route::get('/devices/{device}/checks', [DeviceController::class, 'checks'])->name('devices.checks');
    Route::post('/devices/{device}/recognize', [DeviceController::class, 'recognize'])->name('devices.recognize');

    Route::post('/health-checks', [HealthCheckController::class, 'store'])->name('health-checks.store');
    Route::get('/history', [IncidentController::class, 'index'])->name('history.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::post('/incidents/{incident}/recover', [RecoveryController::class, 'store'])->name('incidents.recover');
    Route::post('/incidents/{incident}/retest', [HealthCheckController::class, 'retest'])->name('incidents.retest');
    Route::patch('/settings', [MonitorSettingController::class, 'update'])->name('settings.update');
    Route::get('/status', StatusController::class)->name('status');
});
