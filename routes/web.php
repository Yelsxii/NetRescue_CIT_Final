<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthCheckController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MonitorSettingController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\StatusController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Local NetRescue Console
|--------------------------------------------------------------------------
| NetRescue is an administrator-only utility. The browser dashboard is used
| only on the computer running NetRescue. All measurements and repairs are local.
*/
Route::middleware('local.console')->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/checks', [HealthCheckController::class, 'index'])->name('checks.index');

    Route::post('/health-checks', [HealthCheckController::class, 'store'])->name('health-checks.store');
    Route::get('/history', [IncidentController::class, 'index'])->name('history.index');
    Route::get('/incidents/{incident}', [IncidentController::class, 'show'])->name('incidents.show');
    Route::post('/incidents/{incident}/recover', [RecoveryController::class, 'store'])->name('incidents.recover');
    Route::post('/incidents/{incident}/retest', [HealthCheckController::class, 'retest'])->name('incidents.retest');
    Route::patch('/settings', [MonitorSettingController::class, 'update'])->name('settings.update');
    Route::get('/status', StatusController::class)->name('status');
});
