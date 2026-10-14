<?php

use App\Http\Controllers\Api\AgentCommandController;
use App\Http\Controllers\Api\AgentDiscoveryController;
use App\Http\Controllers\Api\AgentHealthController;
use App\Http\Controllers\Api\AgentHeartbeatController;
use App\Http\Controllers\Api\AgentRegistrationController;
use Illuminate\Support\Facades\Route;

Route::prefix('agent')->group(function () {
    Route::get('/discover', [AgentDiscoveryController::class, 'show']);
    Route::post('/register', [AgentRegistrationController::class, 'store']);
    Route::post('/heartbeat', [AgentHeartbeatController::class, 'store']);
    Route::post('/health', [AgentHealthController::class, 'store']);
    Route::get('/commands', [AgentCommandController::class, 'index']);
    Route::post('/commands/{command}/result', [AgentCommandController::class, 'result']);
});
