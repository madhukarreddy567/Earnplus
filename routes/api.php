<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CheckinController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\ReferralController;
use App\Http\Controllers\Api\RewardedAdController;
use App\Http\Controllers\Api\SpinController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\WithdrawController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EarnPlus mobile API (Sanctum token auth)
|--------------------------------------------------------------------------
|
| JSON API for the Flutter app. Public endpoints are throttled; everything
| under auth:sanctum requires `Authorization: Bearer <token>`.
|
*/

// Public ---------------------------------------------------------------
Route::get('/config', [ConfigController::class, 'show']);
Route::post('/auth/google', [AuthController::class, 'google'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

// Authenticated ----------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/transactions', [WalletController::class, 'transactions']);

    Route::get('/checkin/status', [CheckinController::class, 'status']);
    Route::post('/checkin', [CheckinController::class, 'store'])->middleware('throttle:10,1');

    Route::get('/spin/status', [SpinController::class, 'status']);
    Route::post('/spin', [SpinController::class, 'spin'])->middleware('throttle:30,1');
    Route::post('/spin/claim', [SpinController::class, 'claim'])->middleware('throttle:30,1');
    Route::get('/spin/claim/{token}', [SpinController::class, 'claimStatus']);

    Route::get('/tasks/providers', [TaskController::class, 'providers']);
    Route::post('/tasks/click/{provider}', [TaskController::class, 'click']);

    Route::get('/promotions', [PromotionController::class, 'index']);

    Route::get('/withdraw/methods', [WithdrawController::class, 'methods']);
    Route::post('/withdraw/quote', [WithdrawController::class, 'quote']);
    Route::post('/withdraw', [WithdrawController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/withdrawals', [WithdrawController::class, 'history']);

    Route::get('/referral', [ReferralController::class, 'show']);

    Route::post('/ads/reward/{placement:slug}', [RewardedAdController::class, 'claim']);
});
