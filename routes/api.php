<?php

use App\Modules\Authentication\Controllers\AuthController;
use App\Modules\Authentication\Controllers\DashboardController;
use App\Modules\ElectroMeter\Controllers\AlertController;
use App\Modules\ElectroMeter\Controllers\AnalyticsController;
use App\Modules\ElectroMeter\Controllers\ApplianceController;
use App\Modules\ElectroMeter\Controllers\EstateController;
use App\Modules\ElectroMeter\Controllers\IoTController;
use App\Modules\ElectroMeter\Controllers\MeterController;
use App\Modules\ElectroMeter\Controllers\RechargeController;
use App\Modules\Subscription\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| VoltWatch API v1 Routes
|--------------------------------------------------------------------------
| Base URL: /api/v1
| Auth: Laravel Sanctum (Bearer token)
|--------------------------------------------------------------------------
*/



// ── Public routes (no auth required) ──────────────────────────────────

Route::prefix('auth')->group(function () {
    Route::post('/register',          [AuthController::class, 'register']);
    Route::post('/login',             [AuthController::class, 'login'])->name('login');
});

// Paystack webhook (signed by Paystack, not Sanctum)
Route::post('/subscriptions/paystack/webhook', [SubscriptionController::class, 'webhook']);

// IoT ingest (signed by shared secret X-IoT-Secret header)
Route::post('/iot/ingest', [IoTController::class, 'ingest']);

// Reference data (public — needed before login for onboarding)
Route::get('/tariff-bands',    [MeterController::class, 'tariffBands']);
Route::get('/appliance-types', [ApplianceController::class, 'types']);


// ── Authenticated routes ───────────────────────────────────────────────

Route::middleware('auth:sanctum')->group(function () {

    // Auth / Profile
    Route::prefix('auth')->group(function () {
        Route::post('/logout',           [AuthController::class, 'logout']);
        Route::get('/me',                [AuthController::class, 'me']);
        Route::patch('/me',              [AuthController::class, 'updateProfile']);
        Route::post('/change-password',  [AuthController::class, 'changePassword']);
    });

    // Dashboard (single call for home screen)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // ── Meters ────────────────────────────────────────────────────────
    Route::apiResource('meters', MeterController::class)->except(['show']);

    // ── Recharges ─────────────────────────────────────────────────────
    Route::prefix('meters/{meter}')->group(function () {
        Route::get('/recharges',          [RechargeController::class, 'index']);
        Route::post('/recharges',         [RechargeController::class, 'store']);

        // Appliances
        Route::get('/appliances',         [ApplianceController::class, 'index']);
        Route::post('/appliances',        [ApplianceController::class, 'store']);

        // Analytics
        Route::get('/analytics',          [AnalyticsController::class, 'index']);  // ?period=week|month|3months|year

        // Alert rules
        Route::get('/alerts',             [AlertController::class, 'rules']);
        Route::post('/alerts',            [AlertController::class, 'store']);
    });

    Route::delete('/recharges/{recharge}',   [RechargeController::class, 'destroy']);
    Route::patch('/appliances/{appliance}',  [ApplianceController::class, 'update']);
    Route::delete('/appliances/{appliance}', [ApplianceController::class, 'destroy']);

    Route::patch('/alerts/{alert}',   [AlertController::class, 'update']);
    Route::delete('/alerts/{alert}',  [AlertController::class, 'destroy']);

    // ── Notifications (alert logs) ─────────────────────────────────────
    Route::get('/notifications',                         [AlertController::class, 'logs']);
    Route::post('/notifications/{log}/read',             [AlertController::class, 'markRead']);
    Route::post('/notifications/read-all',               [AlertController::class, 'markAllRead']);

    // ── Estates ───────────────────────────────────────────────────────
    Route::post('/estates',                              [EstateController::class, 'store']);
    Route::post('/estates/join',                         [EstateController::class, 'join']);
    Route::get('/estates/{estate}/dashboard',            [EstateController::class, 'dashboard']);

    // ── IoT Devices ───────────────────────────────────────────────────
    Route::get('/iot/devices',                           [IoTController::class, 'index']);
    Route::post('/iot/devices/pair',                     [IoTController::class, 'pair']);
    Route::get('/iot/devices/{device}/live',             [IoTController::class, 'live']);

    // ── Subscriptions ─────────────────────────────────────────────────
    Route::get('/subscription',                          [SubscriptionController::class, 'show']);
    Route::post('/subscription/checkout',                [SubscriptionController::class, 'checkout']);
    Route::post('/subscription/cancel',                  [SubscriptionController::class, 'cancel']);
});
