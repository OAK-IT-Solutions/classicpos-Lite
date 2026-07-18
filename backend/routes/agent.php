<?php

use App\Http\Controllers\Api\V1\Agent\AgentDashboardController;
use App\Http\Controllers\Api\V1\Agent\AgentReferralController;
use App\Http\Controllers\Api\V1\Agent\AgentCommissionController;
use App\Http\Controllers\Api\V1\Agent\AgentPayoutController;
use App\Http\Controllers\Api\V1\AgentAuthController;
use Illuminate\Support\Facades\Route;

// Public endpoint: anyone can track a referral click (no auth required)
Route::prefix('agent')->name('agent.')->group(function () {
    Route::post('/referrals/track-click', [AgentReferralController::class, 'trackClick'])->name('referrals.track-click');
});

Route::prefix('agent')->name('agent.')->middleware(['auth:sanctum', 'agent'])->group(function () {

    // Dashboard
    Route::get('/dashboard', [AgentDashboardController::class, 'index'])->name('dashboard');

    // Referrals
    Route::get('/referrals', [AgentReferralController::class, 'index'])->name('referrals.index');
    Route::post('/referrals', [AgentReferralController::class, 'store'])->name('referrals.store');
    Route::get('/referrals/{id}', [AgentReferralController::class, 'show'])->name('referrals.show');
    Route::get('/referrals/{id}/stats', [AgentReferralController::class, 'stats'])->name('referrals.stats');

    // Commissions
    Route::get('/commissions', [AgentCommissionController::class, 'index'])->name('commissions.index');
    Route::get('/commissions/summary', [AgentCommissionController::class, 'summary'])->name('commissions.summary');
    Route::get('/commissions/{id}', [AgentCommissionController::class, 'show'])->name('commissions.show');

    // Payouts
    Route::get('/payouts', [AgentPayoutController::class, 'index'])->name('payouts.index');
    Route::post('/payouts/request', [AgentPayoutController::class, 'requestPayout'])->name('payouts.request');
    Route::get('/payouts/{id}', [AgentPayoutController::class, 'show'])->name('payouts.show');

    // Profile
    Route::get('/profile', [AgentDashboardController::class, 'profile'])->name('profile');
});
