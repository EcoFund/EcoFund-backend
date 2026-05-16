<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DashboardController;
use Illuminate\Support\Facades\Route;



Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login',    [AuthController::class, 'login']);
    Route::post('google',   [AuthController::class, 'googleAuth']); // Google OAuth
});

Route::middleware('auth:sanctum')->get(
    'campaigns/my',
    [CampaignController::class, 'myCampaigns']
);

// ── Campaign & donasi publik ───────────────────────────────────────────
Route::prefix('campaigns')->group(function () {
    Route::get('/', [CampaignController::class, 'index']);
    Route::get('categories', [CampaignController::class, 'categories']);
    Route::get('{campaign}', [CampaignController::class, 'show']);
    Route::get('{campaign}/donations', [DonationController::class, 'campaignDonations']);
});

// ── Webhook payment (dikecualikan dari CSRF di bootstrap/app.php) ──────
Route::post('donations/confirm', [DonationController::class, 'confirm']);

// ── Route butuh login ──────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
        Route::post('me', [AuthController::class, 'updateProfile']);
    });
    Route::prefix('campaigns')->group(function () {
        Route::post('/', [CampaignController::class, 'store']);
        Route::post('{campaign}', [CampaignController::class, 'update']);
        Route::delete('{campaign}', [CampaignController::class, 'destroy']);
        Route::patch('{campaign}/approve', [CampaignController::class, 'approve']);
        Route::patch('{campaign}/reject', [CampaignController::class, 'reject']);
    });

    Route::prefix('donations')->group(function () {
        Route::post('campaigns/{campaign}', [DonationController::class, 'store']);
        Route::get('my', [DonationController::class, 'myDonations']);
    });
});
