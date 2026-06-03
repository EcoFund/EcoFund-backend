<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\CampaignController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\DeleteRequestController;
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
    Route::get('stats', [CampaignController::class, 'stats']);
    Route::get('/', [CampaignController::class, 'index']);
    Route::get('categories', [CampaignController::class, 'categories']);
    Route::get('{campaign}', [CampaignController::class, 'show']);
    Route::get('{campaign}/donations', [DonationController::class, 'campaignDonations']);
    Route::get('{campaign}/images', [CampaignController::class, 'imagesIndex']);
    Route::get('{campaign}/updates', [CampaignController::class, 'updatesIndex']);
});

// ── Webhook & Public Donations ───────────────────────────────────────────
Route::post('donations/campaigns/{campaign}', [DonationController::class, 'store']);
Route::post('donations/confirm', [DonationController::class, 'confirm']);
Route::get('donations/{donation}/check', [DonationController::class, 'checkStatus']); // publik – dipanggil setelah redirect Xendit

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
        Route::post('{campaign}/images', [CampaignController::class, 'imagesStore']);
        Route::delete('{campaign}/images/{image}', [CampaignController::class, 'imagesDestroy']);
        Route::post('{campaign}/updates', [CampaignController::class, 'updatesStore']);
        Route::put('{campaign}/updates/{update}', [CampaignController::class, 'updatesUpdate']);
        Route::delete('{campaign}/updates/{update}', [CampaignController::class, 'updatesDestroy']);
    });

    Route::prefix('donations')->group(function () {
        Route::get('my', [DonationController::class, 'myDonations']);
        Route::get('fundraiser', [DonationController::class, 'fundraiserDonations']);
        Route::get('fundraiser-stats', [DonationController::class, 'fundraiserStats']);
    });

});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('campaigns/{campaign}/delete-request', [DeleteRequestController::class, 'store']);
    Route::get('delete-requests', [DeleteRequestController::class, 'index']);
    Route::patch('delete-requests/{deleteRequest}/approve', [DeleteRequestController::class, 'approve']);
    Route::patch('delete-requests/{deleteRequest}/reject', [DeleteRequestController::class, 'reject']);
});

// ── Admin routes ───────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::get('stats', [AdminController::class, 'stats']);
    Route::get('chart', [AdminController::class, 'adminChart']);
    Route::get('users', [AdminController::class, 'users']);
});

