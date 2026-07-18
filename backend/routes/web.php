<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/offline', function () {
    return Inertia::render('Offline');
})->name('offline');

Route::get('/login', function () {
    return Inertia::render('Login');
})->name('login');

Route::get('/forgot-password', function () {
    return Inertia::render('Auth/ForgotPassword');
})->name('forgot-password');

Route::get('/reset-password', function () {
    return Inertia::render('Auth/ResetPassword');
})->name('reset-password');

Route::get('/register', function () {
    return Inertia::render('Onboarding/Index');
})->name('register');

Route::get('/branch-select', function () {
    return Inertia::render('BranchSelect');
})->name('branch-select');

Route::get('/onboarding/complete', function () {
    return Inertia::render('Onboarding/Complete');
})->name('onboarding.complete');

Route::get('/settings/business', function () {
    return Inertia::render('Settings/Business');
})->name('settings.business');

Route::permanentRedirect('/dashboard', '/');

Route::get('/', function () {
    return Inertia::render('Dashboard');
})->name('dashboard');

Route::get('/pos', function () {
    return Inertia::render('Pos/Register');
})->name('pos');

Route::get('/demo', function () {
    return redirect('/pos');
});

Route::get('/products', fn () => Inertia::render('Products/Index'))->name('products.page');
Route::get('/products/{id}', fn () => Inertia::render('Products/Show'))->name('products.detail');
Route::get('/inventory', fn () => Inertia::render('Inventory/Index'))->name('inventory.page');
Route::get('/inventory/{id}', fn () => Inertia::render('Inventory/Show'))->name('inventory.show');
Route::get('/sales', fn () => Inertia::render('Sales/Index'))->name('sales.page');
Route::get('/sales/{id}', fn () => Inertia::render('Sales/Show'))->name('sales.show');
Route::get('/customers', fn () => Inertia::render('Customers/Index'))->name('customers.page');
Route::get('/customers/{id}', fn (string $id) => Inertia::render('Customers/Show', ['id' => $id]))->name('customers.detail');

Route::get('/settings/users', fn () => Inertia::render('Settings/Users'))->name('settings.users');
Route::get('/settings/roles', fn () => Inertia::render('Settings/Roles'))->name('settings.roles');
Route::get('/settings/branches', fn () => Inertia::render('Settings/Branches'))->name('settings.branches');
Route::get('/settings/devices', fn () => Inertia::render('Settings/Devices'))->name('settings.devices');
Route::get('/settings/printer', fn () => Inertia::render('Settings/Printer'))->name('settings.printer');
Route::get('/settings/subscription', fn () => Inertia::render('Settings/Subscription'))->name('settings.subscription');
Route::get('/settings/downloads', fn () => Inertia::render('Settings/Downloads'))->name('settings.downloads');
Route::get('/settings/warehouses', fn () => Inertia::render('Settings/Warehouses'))->name('settings.warehouses');

Route::get('/cash-register', fn () => Inertia::render('CashRegister/Index'))->name('cash-register.page');
Route::get('/invoices', fn () => Inertia::render('Invoices/Index'))->name('invoices.page');
Route::get('/receipts', fn () => Inertia::render('Receipts/Index'))->name('receipts.page');
Route::get('/reports', fn () => Inertia::render('Reports/Index'))->name('reports.page');
Route::get('/payments', fn () => Inertia::render('Payments/Index'))->name('payments.page');
Route::get('/sync-status', fn () => Inertia::render('SyncStatus/Index'))->name('sync-status.page');
Route::get('/returns', fn () => Inertia::render('Returns/Index'))->name('returns.page');
Route::get('/stock-transfers', fn () => Inertia::render('StockTransfers/Index'))->name('stock-transfers.page');
Route::get('/suppliers', fn () => Inertia::render('Suppliers/Index'))->name('suppliers.page');
Route::get('/purchase-orders', fn () => Inertia::render('PurchaseOrders/Index'))->name('purchase-orders.page');
Route::get('/grn', fn () => Inertia::render('Grn/Index'))->name('grn.page');

Route::get('/settings/promotions', fn () => Inertia::render('Settings/Promotions'))->name('settings.promotions');
Route::get('/settings/tax-profiles', fn () => Inertia::render('Settings/TaxProfiles'))->name('settings.tax-profiles');
Route::get('/settings/loyalty', fn () => Inertia::render('Settings/Loyalty'))->name('settings.loyalty');
Route::get('/settings/profile', fn () => Inertia::render('Settings/Profile'))->name('settings.profile');
Route::get('/suppliers/{id}', fn () => Inertia::render('Suppliers/Show'))->name('suppliers.detail');
Route::get('/purchase-orders/{id}', fn () => Inertia::render('PurchaseOrders/Show'))->name('purchase-orders.detail');
Route::get('/stock-transfers/{id}', fn () => Inertia::render('StockTransfers/Show'))->name('stock-transfers.detail');
Route::get('/returns/{id}', fn (string $id) => Inertia::render('Returns/Show', ['id' => $id]))->name('returns.detail');
Route::get('/grn/{id}', fn () => Inertia::render('Grn/Show'))->name('grn.detail');

// Admin Panel Routes — no server-side auth middleware; auth handled client-side in AdminLayout
Route::prefix('admin')->group(function () {
    Route::get('/login', fn () => Inertia::render('Admin/Login'))->name('admin.login');
    Route::get('/', fn () => Inertia::render('Admin/Dashboard'))->name('admin.dashboard');
    Route::get('/tenants', fn () => Inertia::render('Admin/Tenants/Index'))->name('admin.tenants');
    Route::get('/tenants/{id}', fn (string $id) => Inertia::render('Admin/Tenants/Show', ['id' => $id]))->name('admin.tenants.show');
    Route::get('/plans', fn () => Inertia::render('Admin/Plans/Index'))->name('admin.plans');
    Route::get('/features', fn () => Inertia::render('Admin/Features/Index'))->name('admin.features');
    Route::get('/discounts', fn () => Inertia::render('Admin/Discounts/Index'))->name('admin.discounts');
    Route::get('/subscriptions', fn () => Inertia::render('Admin/Subscriptions/Index'))->name('admin.subscriptions');
    Route::get('/subscriptions/{id}', fn (string $id) => Inertia::render('Admin/Subscriptions/Show', ['id' => $id]))->name('admin.subscriptions.show');
    Route::get('/revenue', fn () => Inertia::render('Admin/Revenue/Index'))->name('admin.revenue');
    Route::get('/agents', fn () => Inertia::render('Admin/Agents/Index'))->name('admin.agents');
    Route::get('/agents/{id}', fn (string $id) => Inertia::render('Admin/Agents/Show', ['id' => $id]))->name('admin.agents.show');
    Route::get('/commissions', fn () => Inertia::render('Admin/Commissions/Index'))->name('admin.commissions');
    Route::get('/tickets', fn () => Inertia::render('Admin/Tickets/Index'))->name('admin.tickets');
    Route::get('/tickets/{id}', fn (string $id) => Inertia::render('Admin/Tickets/Show', ['id' => $id]))->name('admin.tickets.show');
    Route::get('/settings', fn () => Inertia::render('Admin/Settings/Index'))->name('admin.settings');
    Route::get('/admin-users', fn () => Inertia::render('Admin/AdminUsers/Index'))->name('admin.admin-users');
    Route::get('/audit-log', fn () => Inertia::render('Admin/AuditLog/Index'))->name('admin.audit');
    Route::get('/health', fn () => Inertia::render('Admin/Health/Index'))->name('admin.health');
    Route::get('/profile', fn () => Inertia::render('Admin/Profile/Index'))->name('admin.profile');
});

// Tenant Settings
Route::get('/settings/audit-log', fn () => Inertia::render('Settings/AuditLog/Index'))->name('settings.audit-log');

// Accounting pages
Route::get('/chart-of-accounts', fn () => Inertia::render('ChartOfAccounts/Index'))->name('chart-of-accounts');
Route::get('/journal-entries', fn () => Inertia::render('JournalEntries/Index'))->name('journal-entries');
Route::get('/operating-accounts', fn () => Inertia::render('OperatingAccounts/Index'))->name('operating-accounts');
Route::get('/bank-reconciliation', fn () => Inertia::render('BankReconciliation/Index'))->name('bank-reconciliation');

// Billing
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/billing/checkout', fn () => Inertia::render('Billing/Checkout', [
        'planId' => request()->query('plan'),
        'billingCycle' => request()->query('cycle', 'monthly'),
    ]))->name('billing.checkout');
});

// Integrations
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/integrations', fn () => Inertia::render('Integrations/Index'))->name('integrations');
});

// Support Tickets (Merchant-facing) — no server-side auth; handled client-side in AppLayout
Route::get('/support', fn () => Inertia::render('Tickets/Index'))->name('support.tickets');
Route::get('/support/{id}', fn (string $id) => Inertia::render('Tickets/Show', ['id' => $id]))->name('support.ticket');

// Backwards compatibility: /tickets → /support
Route::get('/tickets', fn () => redirect()->route('support.tickets'));
Route::get('/tickets/{id}', fn (string $id) => redirect()->route('support.ticket', $id));

// Agent Portal Routes — public login
Route::prefix('agent')->group(function () {
    Route::get('/login', fn () => Inertia::render('Agent/Login'))->name('agent.login');
    Route::post('/login', [App\Http\Controllers\Web\AgentSessionController::class, 'login']);
    Route::get('/auth/callback', [App\Http\Controllers\Web\AgentSessionController::class, 'callback']);

    // Authenticated routes
    Route::middleware(['auth:sanctum', 'agent'])->group(function () {
        Route::get('/', fn () => Inertia::render('Agent/Dashboard'))->name('agent.dashboard-page');
        Route::get('/referrals', fn () => Inertia::render('Agent/Referrals/Index'))->name('agent.referrals');
        Route::get('/referrals/{id}', fn (string $id) => Inertia::render('Agent/Referrals/Show', ['id' => $id]))->name('agent.referrals.detail');
        Route::get('/commissions', fn () => Inertia::render('Agent/Commissions'))->name('agent.commissions');
        Route::get('/payouts', fn () => Inertia::render('Agent/Payouts'))->name('agent.payouts');
        Route::get('/onboarding', fn () => Inertia::render('Agent/Onboarding'))->name('agent.onboarding');
        Route::get('/profile', fn () => Inertia::render('Agent/Profile'))->name('agent.profile-page');
    });
});
