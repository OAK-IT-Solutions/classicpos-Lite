<?php

namespace Tests;

use Illuminate\Database\Events\DatabaseRefreshed;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class SaaS extends TestCase
{
    use RefreshDatabase;

    protected function refreshDatabase()
    {
        file_put_contents('/tmp/debug_saas3.txt', 'refreshDatabase called' . "\n", FILE_APPEND);
        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            $this->app->make('events')->dispatch(new DatabaseRefreshed);
            RefreshDatabaseState::$migrated = true;
        }
        file_put_contents('/tmp/debug_saas3.txt', 'dropping landlord tables' . "\n", FILE_APPEND);
        $this->dropLandlordTables();
        file_put_contents('/tmp/debug_saas3.txt', 'creating landlord tables' . "\n", FILE_APPEND);
        $this->createLandlordTables();
        file_put_contents('/tmp/debug_saas3.txt', 'beginning transaction' . "\n", FILE_APPEND);
        $this->beginDatabaseTransaction();
        file_put_contents('/tmp/debug_saas3.txt', 'done' . "\n", FILE_APPEND);
    }

    private function dropLandlordTables(): void
    {
        Schema::connection('landlord')->dropIfExists('ticket_messages');
        Schema::connection('landlord')->dropIfExists('support_tickets');
        Schema::connection('landlord')->dropIfExists('agent_referrals');
        Schema::connection('landlord')->dropIfExists('agent_commissions');
        Schema::connection('landlord')->dropIfExists('payment_transactions');
        Schema::connection('landlord')->dropIfExists('plan_feature');
        Schema::connection('landlord')->dropIfExists('tenant_subscriptions');
        Schema::connection('landlord')->dropIfExists('plan_discounts');
        Schema::connection('landlord')->dropIfExists('subscription_features');
        Schema::connection('landlord')->dropIfExists('agents');
        Schema::connection('landlord')->dropIfExists('agent_users');
        Schema::connection('landlord')->dropIfExists('admin_users');
        Schema::connection('landlord')->dropIfExists('subscription_plans');
        Schema::connection('landlord')->dropIfExists('currencies');
        Schema::connection('landlord')->dropIfExists('personal_access_tokens');
        Schema::connection('landlord')->dropIfExists('audit_logs');
        Schema::connection('landlord')->dropIfExists('platform_settings');
        Schema::connection('landlord')->dropIfExists('tenants');
    }

    protected function setUp(): void
    {
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        parent::setUp();
    }

    private function createLandlordTables(): void
    {
        file_put_contents('/tmp/debug_saas2.txt', 'creating tenants' . "\n", FILE_APPEND);
        Schema::connection('landlord')->create('tenants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('domain')->nullable()->unique();
            $table->string('subdomain', 63)->nullable()->unique();
            $table->enum('status', ['pending', 'trial', 'active', 'suspended', 'cancelled'])->default('pending');
            $table->string('db_host')->nullable();
            $table->integer('db_port')->nullable();
            $table->string('db_name')->nullable();
            $table->string('db_username')->nullable();
            $table->string('db_password')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('business_name')->nullable();
            $table->string('business_email')->nullable();
            $table->string('business_phone')->nullable();
            $table->uuid('created_by_agent_id')->nullable();
            $table->uuid('referred_by_agent_id')->nullable();
            $table->json('metadata')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('landlord')->create('subscription_plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->decimal('price_monthly', 10, 2);
            $table->decimal('price_yearly', 10, 2);
            $table->text('description')->nullable();
            $table->bigInteger('max_branches')->default(1);
            $table->bigInteger('max_users_per_branch')->default(3);
            $table->bigInteger('max_devices_per_branch')->default(2);
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_popular')->default(false);
            $table->string('highlight_color', 20)->nullable();
            $table->string('cta_text', 100)->nullable();
            $table->decimal('discount_percent_yearly', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::connection('landlord')->create('admin_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['super_admin', 'admin', 'support'])->default('admin');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->index('role');
            $table->index('is_active');
        });

        Schema::connection('landlord')->create('agent_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::connection('landlord')->create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10);
            $table->enum('tier', ['standard', 'silver', 'gold', 'platinum'])->default('standard');
            $table->decimal('tier_threshold', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();
            $table->integer('total_referrals')->default(0);
            $table->integer('converted_referrals')->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('pending_earnings', 12, 2)->default(0);
            $table->decimal('paid_earnings', 12, 2)->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('landlord')->create('subscription_features', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->string('group_name', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::connection('landlord')->create('plan_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('name');
            $table->string('code', 50)->unique();
            $table->enum('type', ['percentage', 'fixed'])->default('percentage');
            $table->decimal('value', 10, 2);
            $table->enum('billing_cycle', ['monthly', 'yearly'])->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('max_uses')->nullable();
            $table->integer('current_uses')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->timestamps();
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
            $table->index(['plan_id', 'is_active']);
        });

        Schema::connection('landlord')->create('currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->string('symbol', 10);
            $table->decimal('exchange_rate_to_usd', 12, 6)->default(1);
            $table->integer('decimal_places')->default(2);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::connection('landlord')->create('plan_feature', function (Blueprint $table) {
            $table->uuid('plan_id');
            $table->uuid('feature_id');
            $table->boolean('is_highlighted')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->primary(['plan_id', 'feature_id']);
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
            $table->foreign('feature_id')->references('id')->on('subscription_features')->onDelete('cascade');
        });

        Schema::connection('landlord')->create('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('plan_id');
            $table->uuid('subscription_plan_id')->nullable();
            $table->enum('status', ['active', 'trialing', 'past_due', 'cancelled', 'expired'])->default('trialing');
            $table->enum('billing_cycle', ['monthly', 'yearly'])->default('monthly');
            $table->decimal('amount', 12, 2)->nullable();
            $table->decimal('original_amount', 12, 2)->nullable();
            $table->uuid('discount_id')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('past_due_at')->nullable();
            $table->string('paypal_order_id')->nullable();
            $table->string('paypal_subscription_id')->nullable();
            $table->string('pesapal_subscription_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('subscription_plans');
            $table->foreign('discount_id')->references('id')->on('plan_discounts')->onDelete('set null');
        });

        Schema::connection('landlord')->create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->nullable();
            $table->uuid('subscription_id')->nullable();
            $table->string('type', 50)->default('subscription');
            $table->uuid('agent_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('KES');
            $table->string('gateway')->default('pesapal');
            $table->string('gateway_ref')->nullable();
            $table->string('order_tracking_id')->nullable();
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'refunded', 'voided'])->default('pending');
            $table->string('description')->nullable();
            $table->string('invoice_number')->nullable();
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('tenant_subscriptions')->onDelete('set null');
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('set null');
            $table->index(['tenant_id', 'status']);
            $table->index('gateway_ref');
            $table->index('order_tracking_id');
        });

        Schema::connection('landlord')->create('agent_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->uuid('tenant_id')->nullable();
            $table->uuid('subscription_id')->nullable();
            $table->uuid('payment_transaction_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->decimal('rate', 5, 2);
            $table->string('type')->default('subscription_referral');
            $table->enum('status', ['pending', 'cleared', 'paid', 'rejected'])->default('pending');
            $table->timestamp('cleared_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payout_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('set null');
            $table->index(['agent_id', 'status']);
        });

        Schema::connection('landlord')->create('agent_referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->uuid('tenant_id')->nullable();
            $table->string('referral_code', 32);
            $table->string('landing_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('first_payment_at')->nullable();
            $table->decimal('commission_earned', 12, 2)->default(0);
            $table->boolean('commission_paid')->default(false);
            $table->timestamps();
            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique('referral_code');
            $table->index(['agent_id', 'converted_at']);
        });

        Schema::connection('landlord')->create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable();
            $table->string('subject');
            $table->text('description');
            $table->string('ticket_number', 32)->unique();
            $table->enum('status', ['open', 'in_progress', 'waiting_reply', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('category', ['billing', 'technical', 'feature_request', 'general', 'bug_report'])->default('general');
            $table->uuid('assigned_to')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->integer('message_count')->default(0);
            $table->integer('unread_count')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('assigned_to');
        });

        Schema::connection('landlord')->create('ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');
            $table->uuid('user_id')->nullable();
            $table->string('user_type', 50)->default('tenant_user');
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->boolean('is_read')->default(false);
            $table->json('attachments')->nullable();
            $table->timestamps();
            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->index(['ticket_id', 'created_at']);
        });

        Schema::connection('landlord')->create('platform_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('group')->default('general');
            $table->string('type')->default('string');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::connection('landlord')->create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('user_id')->nullable()->uuid();
            $table->enum('user_type', ['admin', 'agent', 'tenant_user', 'system'])->default('system');
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('action');
            $table->string('action_group')->default('general');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable()->uuid();
            $table->string('subject_description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['user_id', 'user_type']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action_group');
            $table->index('created_at');
        });

        Schema::connection('landlord')->create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->uuidMorphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->index('tokenable_id');
        });
    }
}
