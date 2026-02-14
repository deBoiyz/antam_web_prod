<?php

use App\Http\Controllers\Api\BotConfigController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\SessionController;
use App\Http\Controllers\Api\ProxyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes for Bot Engine
|--------------------------------------------------------------------------
*/

// Bot Configuration
Route::prefix('config')->group(function () {
    Route::get('/websites', [BotConfigController::class, 'getAllConfigs']);
    Route::get('/websites/all', [BotConfigController::class, 'getAllConfigsIncludingInactive']);
    Route::get('/websites/{id}', [BotConfigController::class, 'getWebsiteConfig'])->where('id', '[0-9]+');
    Route::get('/websites/slug/{slug}', [BotConfigController::class, 'getWebsiteConfigBySlug']);
    Route::get('/captcha-services', [BotConfigController::class, 'getCaptchaServices']);
    Route::get('/proxies', [BotConfigController::class, 'getProxies']);
});

// Job Management
Route::prefix('jobs')->group(function () {
    Route::get('/next', [JobController::class, 'getNextBatch']);
    Route::get('/stats', [JobController::class, 'getStats']);
    Route::get('/{id}', [JobController::class, 'getJob']);
    Route::post('/{id}/processing', [JobController::class, 'markProcessing']);
    Route::post('/{id}/success', [JobController::class, 'markSuccess']);
    Route::post('/{id}/failed', [JobController::class, 'markFailed']);
});

// Logging
Route::prefix('logs')->group(function () {
    Route::get('/recent', [LogController::class, 'getRecent']);
    Route::post('/start', [LogController::class, 'logStart']);
    Route::post('/step', [LogController::class, 'logStep']);
    Route::post('/success', [LogController::class, 'logSuccess']);
    Route::post('/failure', [LogController::class, 'logFailure']);
});

// Session Management
Route::prefix('sessions')->group(function () {
    Route::get('/active', [SessionController::class, 'getActive']);
    Route::post('/register', [SessionController::class, 'register']);
    Route::post('/cleanup-stale', [SessionController::class, 'cleanupStale']);
    Route::post('/{sessionId}/status', [SessionController::class, 'updateStatus']);
    Route::post('/{sessionId}/completion', [SessionController::class, 'recordCompletion']);
    Route::post('/{sessionId}/heartbeat', [SessionController::class, 'heartbeat']);
    Route::delete('/{sessionId}', [SessionController::class, 'unregister']);
});

// Proxy Management
Route::prefix('proxy')->group(function () {
    Route::get('/next', [ProxyController::class, 'getNext']);
    Route::post('/{id}/success', [ProxyController::class, 'reportSuccess']);
    Route::post('/{id}/failure', [ProxyController::class, 'reportFailure']);
});

// CAPTCHA Service Reporting
Route::prefix('captcha')->group(function () {
    Route::post('/{id}/success', [ProxyController::class, 'reportCaptchaSuccess']);
    Route::post('/{id}/failure', [ProxyController::class, 'reportCaptchaFailure']);
});
