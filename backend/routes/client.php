<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Client\ClientAuthController;
use App\Http\Controllers\Api\V1\Client\ClientDashboardController;
use App\Http\Controllers\Api\V1\Client\ClientSubscriptionController;
use App\Http\Controllers\Api\V1\Client\ClientServiceController;
use App\Http\Controllers\Api\V1\Client\ClientTicketController;
use App\Http\Controllers\Api\V1\Client\ClientBillingController;

// Client Auth (no auth required)
Route::prefix('v1/client/auth')->middleware('throttle:10,1')->group(function () {
    Route::post('register', [ClientAuthController::class, 'register']);
    Route::post('login', [ClientAuthController::class, 'login']);
    Route::post('generate-verification-token', [ClientAuthController::class, 'generateVerificationToken']);
    Route::post('verify-email', [ClientAuthController::class, 'verifyEmail']);
});

// Public: list available plans and services
Route::get('v1/client/plans', function () {
    return \App\Models\Landlord\SubscriptionPlan::where('type', 'oakit')
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();
});

Route::get('v1/client/services', [ClientServiceController::class, 'index']);

// Client protected routes
Route::prefix('v1/client')->middleware(['auth:sanctum', 'client', 'throttle:120,1'])->group(function () {

    // Auth
    Route::get('auth/me', [ClientAuthController::class, 'me']);
    Route::put('auth/profile', [ClientAuthController::class, 'updateProfile']);
    Route::put('auth/change-password', [ClientAuthController::class, 'changePassword']);
    Route::post('auth/logout', [ClientAuthController::class, 'logout']);

    // Dashboard
    Route::get('dashboard', [ClientDashboardController::class, 'index']);

    // Subscriptions
    Route::get('subscriptions', [ClientSubscriptionController::class, 'index']);
    Route::get('subscriptions/{id}', [ClientSubscriptionController::class, 'show']);

    // Subscribed services
    Route::get('services/subscribed', [ClientServiceController::class, 'subscribed']);

    // Tickets
    Route::get('tickets', [ClientTicketController::class, 'index']);
    Route::post('tickets', [ClientTicketController::class, 'store']);
    Route::get('tickets/{id}', [ClientTicketController::class, 'show']);
    Route::post('tickets/{id}/reply', [ClientTicketController::class, 'reply']);

    // Billing
    Route::get('billing', [ClientBillingController::class, 'index']);
});
