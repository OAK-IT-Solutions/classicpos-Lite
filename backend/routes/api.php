<?php

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\SalesController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\BranchController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\SyncSalesController;
use App\Http\Controllers\Api\V1\SyncSettingsController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\RoleController;
use App\Http\Controllers\Api\V1\CashRegisterController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\ExpenseController;
use App\Http\Controllers\Api\V1\InventoryAdjustmentController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\WarehouseController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\PurchaseOrderController;
use App\Http\Controllers\Api\V1\GrnController;
use App\Http\Controllers\Api\V1\AgentAuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\PayPalController;
use App\Http\Controllers\Api\V1\TicketController;
use App\Http\Controllers\Api\V1\StockTransferController;
use App\Http\Controllers\Api\V1\ReturnController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\PromotionController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\TaxProfileController;
use App\Http\Controllers\Api\V1\LoyaltyController;
use App\Http\Controllers\Api\V1\LocaleController;
use App\Http\Controllers\Api\V1\ChartOfAccountController;
use App\Http\Controllers\Api\V1\JournalEntryController;
use App\Http\Controllers\Api\V1\OperatingAccountController;
use App\Http\Controllers\Api\V1\BankReconciliationController;
use App\Http\Controllers\Api\V1\IntegrationController;
use App\Http\Controllers\Api\V1\EfrisController;

use App\Http\Controllers\Api\V1\PublicPlanController;
use App\Http\Controllers\Api\V1\ActivityLogController;

Route::prefix('v1')->group(function () {
    // Auth
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:3,60');
    Route::post('/auth/forgot-password/secret-question', [AuthController::class, 'getSecretQuestion'])->middleware('throttle:5,1');
    Route::post('/auth/forgot-password/verify-secret', [AuthController::class, 'verifySecret'])->middleware('throttle:5,1');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:3,60');

    // Agent Auth (public — no auth required)
    Route::post('/agent/auth/register', [AgentAuthController::class, 'register'])->middleware('throttle:5,1');
    Route::post('/agent/auth/login', [AgentAuthController::class, 'login'])->middleware('throttle:10,1');

    // Health check (unauthenticated)
    Route::get('/health', [\App\Http\Controllers\Api\V1\HealthController::class, 'check']);

    // Public plans & currencies (unauthenticated)
    Route::get('/plans', [PublicPlanController::class, 'index']);
    Route::get('/plans/{slug}', [PublicPlanController::class, 'show']);
    Route::get('/currencies', [PublicPlanController::class, 'currencies']);

    // Desktop license endpoints (unauthenticated — used before login)
    Route::prefix('desktop')->group(function () {
        Route::post('/license/verify', [\App\Http\Controllers\Api\V1\DesktopLicenseController::class, 'verify']);
        Route::post('/license/activate', [\App\Http\Controllers\Api\V1\DesktopLicenseController::class, 'activate']);
        Route::get('/license/status', [\App\Http\Controllers\Api\V1\DesktopLicenseController::class, 'status']);
        Route::post('/license/deactivate', [\App\Http\Controllers\Api\V1\DesktopLicenseController::class, 'deactivate']);
        Route::get('/license/generate-demo', [\App\Http\Controllers\Api\V1\DesktopLicenseController::class, 'generateDemo']);
        // License purchase
        Route::post('/license/purchase', [\App\Http\Controllers\Api\V1\DesktopLicensePurchaseController::class, 'purchase']);
        Route::post('/license/complete', [\App\Http\Controllers\Api\V1\DesktopLicensePurchaseController::class, 'complete']);
        Route::get('/license/plans', [\App\Http\Controllers\Api\V1\DesktopLicensePurchaseController::class, 'plans']);
        // Update server for Tauri Updater
        Route::get('/updater/{target}/{arch}/{current_version}', [\App\Http\Controllers\Api\V1\DesktopUpdateController::class, 'check']);
    });

    // Protected routes
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/auth/email/verification-notification', [AuthController::class, 'sendEmailVerification'])->middleware('throttle:3,60');
        Route::get('/auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');

        // User profile
        Route::get('/auth/profile', [AuthController::class, 'profile']);
        Route::put('/auth/profile', [AuthController::class, 'updateProfile']);
        Route::put('/auth/profile/secret-question', [AuthController::class, 'setSecretQuestion']);

        // Onboarding — any authenticated user
        Route::get('/onboarding/status', [OnboardingController::class, 'status']);
        Route::post('/onboarding/complete', [OnboardingController::class, 'complete']);

        // Onboarding profile — requires manage_business_profile
        Route::put('/onboarding/profile', [OnboardingController::class, 'updateProfile'])
            ->middleware('permission:manage_business_profile');

        // Locale settings — requires manage_business_profile
        Route::middleware('permission:manage_business_profile')->group(function () {
            Route::get('/settings/locale', [LocaleController::class, 'show']);
            Route::put('/settings/locale', [LocaleController::class, 'update']);
        });

        // Sales — requires manage_sales
        Route::middleware('permission:manage_sales')->group(function () {
            Route::get('/sales', [SalesController::class, 'index']);
            Route::post('/sales', [SalesController::class, 'store']);
            Route::get('/sales/{sale}', [SalesController::class, 'show']);
            Route::post('/sales/{sale}/email-invoice', [SalesController::class, 'emailInvoice']);

            // Void requires void_sale permission
            Route::post('/sales/{sale}/void', [SalesController::class, 'void'])
                ->middleware('permission:void_sale');

            // Cash Register
            Route::get('/cash-register/status', [CashRegisterController::class, 'status']);
            Route::post('/cash-register/open', [CashRegisterController::class, 'open']);
            Route::post('/cash-register/{shift}/close', [CashRegisterController::class, 'close']);
            Route::get('/cash-register/shifts', [CashRegisterController::class, 'shifts']);
            Route::get('/cash-register/shifts/{shift}', [CashRegisterController::class, 'showShift']);

            // POS
            Route::get('/pos/products', [PosController::class, 'products']);
            Route::post('/pos/hold', [PosController::class, 'hold']);
            Route::get('/pos/held', [PosController::class, 'held']);
            Route::post('/pos/resume/{holdSale}', [PosController::class, 'resume']);

            // Returns
            Route::post('/returns/{return}/approve', [ReturnController::class, 'approve'])
                ->middleware('permission:approve_return');
            Route::apiResource('/returns', ReturnController::class);

            // Quotes & Invoices
            Route::post('/documents/{id}/convert-to-invoice', [DocumentController::class, 'convertToInvoice']);
            Route::post('/documents/{id}/status', [DocumentController::class, 'updateStatus']);
            Route::post('/documents/{id}/payments', [DocumentController::class, 'recordPayment']);
            Route::apiResource('/documents', DocumentController::class);
        });

        // Inventory — requires manage_inventory
        Route::middleware('permission:manage_inventory')->group(function () {
            Route::put('/inventory/update', [InventoryController::class, 'update']);
            Route::get('/inventory/stock', [InventoryController::class, 'stock']);
            Route::get('/inventory/{inventory}/movements', [InventoryController::class, 'movements']);
            Route::apiResource('/warehouses', WarehouseController::class);
            Route::apiResource('/suppliers', SupplierController::class);
            Route::put('/purchase-orders/{purchase_order}/status', [PurchaseOrderController::class, 'transitionStatus']);
            Route::apiResource('/purchase-orders', PurchaseOrderController::class);
            Route::apiResource('/grn', GrnController::class);
            Route::post('/stock-transfers/{stock_transfer}/complete', [StockTransferController::class, 'complete']);
            Route::post('/stock-transfers/{stock_transfer}/cancel', [StockTransferController::class, 'cancel']);
            Route::apiResource('/stock-transfers', StockTransferController::class);
            Route::get('/inventory-adjustments/types', [InventoryAdjustmentController::class, 'types']);
            Route::apiResource('/inventory-adjustments', InventoryAdjustmentController::class);
        });

        // Payments (sale receipts) — requires process_payments
        Route::middleware('permission:process_payments')->group(function () {
            Route::get('/payments', [PaymentController::class, 'index']);
            Route::post('/payments/process', [PaymentController::class, 'process']);
            Route::post('/payments/rollback/{sale}', [PaymentController::class, 'rollback']);
        });

        // Expenses (money going out) — requires manage_inventory
        Route::middleware('permission:manage_inventory')->group(function () {
            Route::get('/expenses/summary', [ExpenseController::class, 'summary']);
            Route::apiResource('/expenses', ExpenseController::class);
        });

        // Customers — requires manage_customers
        Route::middleware('permission:manage_customers')->group(function () {
            Route::get('/customers/trashed', [CustomerController::class, 'trashed']);
            Route::get('/customers/{id}/stats', [CustomerController::class, 'stats']);
            Route::post('/customers/{id}/restore', [CustomerController::class, 'restore']);
            Route::apiResource('/customers', CustomerController::class);
        });

        // Products & Categories — requires manage_products
        Route::middleware('permission:manage_products')->group(function () {
            Route::get('/categories', [CategoryController::class, 'index']);
            Route::post('/categories', [CategoryController::class, 'store']);
            Route::put('/categories/{category}', [CategoryController::class, 'update']);
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
            Route::get('/products/by-barcode/{barcode}', [ProductController::class, 'byBarcode']);
            Route::apiResource('/products', ProductController::class);
            Route::post('/products/{product}/upload-image', [ProductController::class, 'uploadImage']);
            Route::delete('/products/{product}/image', [ProductController::class, 'deleteImage']);
            Route::apiResource('/promotions', PromotionController::class);
            Route::apiResource('/tax-profiles', TaxProfileController::class);
            Route::get('/loyalty/current', [LoyaltyController::class, 'current']);
            Route::get('/loyalty/customer-points', [LoyaltyController::class, 'customerPoints']);
            Route::post('/loyalty/redeem', [LoyaltyController::class, 'redeem']);
            Route::apiResource('/loyalty', LoyaltyController::class);
        });

        // Reports — requires view_reports (and export_data for CSV export)
        Route::middleware('permission:view_reports')->group(function () {
            Route::get('/reports/summary', [ReportController::class, 'summary']);
            Route::get('/reports/sales-trend', [ReportController::class, 'salesTrend']);
            Route::get('/reports/top-products', [ReportController::class, 'topProducts']);
            Route::get('/reports/revenue-by-payment', [ReportController::class, 'revenueByPayment']);
            Route::get('/reports/daily-revenue', [ReportController::class, 'dailyRevenue']);
            Route::get('/reports/tax-report', [ReportController::class, 'taxReport']);
            Route::get('/reports/profit-loss', [ReportController::class, 'profitLoss']);
            Route::get('/reports/sales-journal', [ReportController::class, 'salesJournal']);
            Route::get('/reports/inventory-movement', [ReportController::class, 'inventoryMovement']);
            Route::get('/reports/customer-statement', [ReportController::class, 'customerStatement']);
            Route::get('/reports/sales-by-user', [ReportController::class, 'salesByUser']);
            Route::get('/reports/cogs', [ReportController::class, 'costOfGoodsSold']);
            Route::get('/reports/payments-reconciliation', [ReportController::class, 'paymentsReconciliation']);
            Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance']);
            Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet']);
            Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement']);
            Route::get('/reports/general-ledger', [ReportController::class, 'generalLedger']);
            Route::get('/reports/low-stock', [ReportController::class, 'lowStock']);
            Route::get('/reports/inventory-valuation', [ReportController::class, 'inventoryValuation']);
            Route::get('/reports/purchase-orders', [ReportController::class, 'purchaseOrders']);
            Route::get('/reports/loyalty-points', [ReportController::class, 'loyaltyPoints']);
        });

        // Accounting — requires manage_accounting
        Route::middleware('permission:manage_accounting')->group(function () {
            Route::apiResource('/chart-of-accounts', ChartOfAccountController::class);
            Route::apiResource('/journal-entries', JournalEntryController::class)->only(['index', 'show', 'store']);
            Route::apiResource('/operating-accounts', OperatingAccountController::class);
            Route::get('/bank-reconciliations', [BankReconciliationController::class, 'index']);
            Route::post('/bank-reconciliations', [BankReconciliationController::class, 'store']);
            Route::get('/bank-reconciliations/{bank_reconciliation}', [BankReconciliationController::class, 'show']);
            Route::post('/bank-reconciliations/{bank_reconciliation}/complete', [BankReconciliationController::class, 'complete']);
            Route::post('/bank-reconciliations/{bank_reconciliation}/items', [BankReconciliationController::class, 'addItem']);
            Route::delete('/bank-reconciliations/{bank_reconciliation}/items/{item}', [BankReconciliationController::class, 'removeItem']);
        });

        // Sync — any authenticated user (no extra permission)
        Route::post('/sync/start', [SyncController::class, 'start']);
        Route::get('/sync/status', [SyncController::class, 'status']);
        Route::post('/sync/sales', [SyncSalesController::class, 'store']);
        Route::get('/sync/settings', [SyncSettingsController::class, 'show']);
        Route::put('/sync/settings', [SyncSettingsController::class, 'update']);

        // Admin-only routes
        Route::middleware('role:admin')->group(function () {
            Route::apiResource('/branches', BranchController::class);

            // Role & Permission management
            Route::get('/permissions', [RoleController::class, 'allPermissions']);
            Route::get('/roles', [RoleController::class, 'index']);
            Route::post('/roles', [RoleController::class, 'store']);
            Route::get('/roles/{role}', [RoleController::class, 'show']);
            Route::put('/roles/{role}', [RoleController::class, 'update']);
            Route::delete('/roles/{role}', [RoleController::class, 'destroy']);
            Route::put('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);

            Route::get('/users/roles/{user}', [UserController::class, 'roles']);
            Route::post('/users/assign-role', [UserController::class, 'assignRole']);
            Route::post('/users/revoke-role', [UserController::class, 'revokeRole']);
            Route::apiResource('/users', UserController::class);

            Route::post('/devices/enroll', [DeviceController::class, 'enroll']);
            Route::post('/devices/heartbeat', [DeviceController::class, 'heartbeat']);
            Route::apiResource('/devices', DeviceController::class);

            Route::get('/subscriptions', [SubscriptionController::class, 'index']);
            Route::get('/subscriptions/current', [SubscriptionController::class, 'current']);
            Route::get('/subscriptions/plans', [SubscriptionController::class, 'availablePlans']);
            Route::put('/subscriptions', [SubscriptionController::class, 'update']);
            Route::post('/subscriptions/cancel', [SubscriptionController::class, 'cancel'])
                ->middleware('permission:manage_subscription');
        });

        // Billing & Subscriptions
        Route::post('/billing/checkout', [BillingController::class, 'checkout']);
        Route::get('/billing/status/{orderId}', [BillingController::class, 'status']);
        Route::get('/billing/history', [BillingController::class, 'history']);
        Route::get('/billing/subscription', [BillingController::class, 'currentSubscription']);

        // PayPal (auth)
        Route::prefix('billing/paypal')->group(function () {
            Route::post('/create-order', [PayPalController::class, 'createOrder']);
            Route::post('/capture/{orderId}', [PayPalController::class, 'captureOrder']);
            Route::get('/status/{orderId}', [PayPalController::class, 'status']);
        });

        // Pesapal webhooks (no auth — called by Pesapal servers)
        Route::get('/billing/callback', [BillingController::class, 'callback'])->withoutMiddleware(['auth:sanctum']);
        Route::get('/billing/ipn', [BillingController::class, 'ipn'])->withoutMiddleware(['auth:sanctum']);

        // PayPal webhook (no auth — called by PayPal servers)
        Route::post('/billing/paypal/webhook', [PayPalController::class, 'webhook'])->withoutMiddleware(['auth:sanctum']);

        // Support Tickets (merchant-facing)
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store'])->middleware('throttle:5,1');
        Route::get('/tickets/{id}', [TicketController::class, 'show']);
        Route::post('/tickets/{id}/reply', [TicketController::class, 'reply']);
        Route::post('/tickets/{id}/close', [TicketController::class, 'close']);
        Route::post('/tickets/{id}/reopen', [TicketController::class, 'reopen']);

        // Integrations
        Route::get('/integrations/available', [IntegrationController::class, 'available']);
        Route::apiResource('integrations', IntegrationController::class);
        Route::post('/integrations/{integration}/test', [IntegrationController::class, 'testConnection']);
        Route::post('/integrations/{integration}/sync', [IntegrationController::class, 'sync']);
        Route::get('/integrations/{integration}/logs', [IntegrationController::class, 'logs']);

        // EFRIS (Uganda Tax Integration)
        Route::post('/efris/fiscalize/{sale}', [EfrisController::class, 'fiscalizeSale']);
        Route::post('/efris/invoices/query', [EfrisController::class, 'queryInvoices']);
        Route::get('/efris/invoices/{invoiceNo}', [EfrisController::class, 'invoiceDetails']);
        Route::post('/efris/credit-note', [EfrisController::class, 'applyCreditNote']);
        Route::post('/efris/products/sync', [EfrisController::class, 'syncProducts']);
        Route::post('/efris/products/register', [EfrisController::class, 'registerProducts']);
        Route::post('/efris/stock/increase', [EfrisController::class, 'increaseStock']);
        Route::post('/efris/stock/decrease', [EfrisController::class, 'decreaseStock']);
        Route::post('/efris/stock/transfer', [EfrisController::class, 'transferStock']);
        Route::post('/efris/taxpayer/search', [EfrisController::class, 'searchTaxpayer']);
        Route::get('/efris/registration', [EfrisController::class, 'registrationDetails']);
        Route::get('/efris/logs', [EfrisController::class, 'fiscalLogs']);

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
        Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

        // Activity Logs (tenant-level audit trail)
        Route::get('/activity-logs', [ActivityLogController::class, 'index'])
            ->middleware('permission:manage_settings');
        Route::get('/activity-logs/export', [ActivityLogController::class, 'export'])
            ->middleware('permission:manage_settings');

        // Agent Auth (authenticated)
        Route::get('/agent/auth/me', [AgentAuthController::class, 'me']);
        Route::post('/agent/auth/logout', [AgentAuthController::class, 'logout']);

        // Agent Profile
        Route::get('/agent/auth/profile', [AgentAuthController::class, 'profile']);
        Route::put('/agent/auth/profile', [AgentAuthController::class, 'updateProfile']);
        Route::put('/agent/auth/change-password', [AgentAuthController::class, 'changePassword']);
    });
});
