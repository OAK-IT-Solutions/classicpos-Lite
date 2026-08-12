<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Admin\TenantController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionPlanController;
use App\Http\Controllers\Api\V1\Admin\AdminSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\RevenueController;
use App\Http\Controllers\Api\V1\Admin\AgentController;
use App\Http\Controllers\Api\V1\Admin\CommissionController;
use App\Http\Controllers\Api\V1\Admin\PlatformSettingController;
use App\Http\Controllers\Api\V1\Admin\SystemHealthController;
use App\Http\Controllers\Api\V1\Admin\AuditLogController;
use App\Http\Controllers\Api\V1\Admin\AdminTicketController;
use App\Http\Controllers\Api\V1\Admin\FeatureController;
use App\Http\Controllers\Api\V1\Admin\PlanDiscountController;
use App\Http\Controllers\Api\V1\Admin\AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\AdminUserController;

// Admin Auth (no auth required)
Route::prefix('v1/admin/auth')->middleware('throttle:10,1')->group(function () {
    Route::post('login', [AdminAuthController::class, 'login']);
});

Route::prefix('v1/admin')->middleware(['auth:sanctum', 'super_admin', 'throttle:120,1'])->group(function () {

    // Admin Auth (authenticated)
    Route::get('auth/me', [AdminAuthController::class, 'me']);
    Route::get('auth/profile', [AdminAuthController::class, 'profile']);
    Route::put('auth/profile', [AdminAuthController::class, 'updateProfile']);
    Route::put('auth/change-password', [AdminAuthController::class, 'changePassword']);
    Route::post('auth/logout', [AdminAuthController::class, 'logout']);

    // Admin Users
    Route::get('admin-users', [AdminUserController::class, 'index']);
    Route::post('admin-users', [AdminUserController::class, 'store']);
    Route::put('admin-users/{admin}', [AdminUserController::class, 'update']);
    Route::delete('admin-users/{admin}', [AdminUserController::class, 'destroy']);

    // Dashboard
    Route::get('/dashboard', [RevenueController::class, 'dashboard']);

    // Tenants
    Route::apiResource('tenants', TenantController::class);
    Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend']);
    Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate']);
    Route::post('tenants/{tenant}/cancel', [TenantController::class, 'cancel']);
    Route::post('tenants/{tenant}/impersonate', [TenantController::class, 'impersonate']);

    // Features
    Route::apiResource('features', FeatureController::class);

    // Plan Discounts
    Route::apiResource('discounts', PlanDiscountController::class);

    // Subscription Plans
    Route::apiResource('plans', SubscriptionPlanController::class);

    // Subscriptions (cross-tenant view)
    Route::get('subscriptions', [AdminSubscriptionController::class, 'index']);
    Route::get('subscriptions/{subscription}', [AdminSubscriptionController::class, 'show']);
    Route::put('subscriptions/{subscription}/change-plan', [AdminSubscriptionController::class, 'changePlan']);
    Route::post('subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel']);

    // Revenue Analytics
    Route::get('revenue/summary', [RevenueController::class, 'summary']);
    Route::get('revenue/mrr', [RevenueController::class, 'mrr']);
    Route::get('revenue/arr', [RevenueController::class, 'arr']);
    Route::get('revenue/churn', [RevenueController::class, 'churn']);
    Route::get('revenue/ltv', [RevenueController::class, 'ltv']);
    Route::get('revenue/trend', [RevenueController::class, 'trend']);
    Route::get('revenue/by-plan', [RevenueController::class, 'byPlan']);

    // Agents
    Route::apiResource('agents', AgentController::class);
    Route::get('agents/{agent}/performance', [AgentController::class, 'performance']);

    // Commissions
    Route::get('commissions', [CommissionController::class, 'index']);
    Route::get('commissions/summary', [CommissionController::class, 'summary']);
    Route::post('commissions/{commission}/approve', [CommissionController::class, 'approve']);
    Route::post('commissions/{commission}/pay', [CommissionController::class, 'pay']);

    // Support Tickets
    Route::get('tickets', [AdminTicketController::class, 'index']);
    Route::get('tickets/{ticket}', [AdminTicketController::class, 'show']);
    Route::post('tickets/{ticket}/assign', [AdminTicketController::class, 'assign']);
    Route::post('tickets/{ticket}/reply', [AdminTicketController::class, 'reply']);
    Route::put('tickets/{ticket}/status', [AdminTicketController::class, 'updateStatus']);
    Route::post('tickets/{ticket}/reopen', [AdminTicketController::class, 'reopen']);

    // Platform Settings
    Route::get('settings', [PlatformSettingController::class, 'index']);
    Route::put('settings', [PlatformSettingController::class, 'update']);

    // System Health
    Route::get('health', [SystemHealthController::class, 'status']);

    // Audit Logs
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/export', [AuditLogController::class, 'export']);

    // API Documentation (Swagger UI)
    Route::get('docs', [\L5Swagger\Http\Controllers\SwaggerController::class, 'api'])->name('admin.docs');
});
