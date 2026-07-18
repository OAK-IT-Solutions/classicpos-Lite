<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('subscription_id')->nullable();
            $table->uuid('agent_id')->nullable();

            // Payment details
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('KES');
            $table->string('gateway')->default('pesapal');
            $table->string('gateway_ref')->nullable();
            $table->string('order_tracking_id')->nullable();

            // Status
            $table->enum('status', ['pending', 'processing', 'success', 'failed', 'refunded', 'voided'])->default('pending');

            // Description
            $table->string('description')->nullable();
            $table->string('invoice_number')->nullable();

            // Gateway response
            $table->json('gateway_response')->nullable();
            $table->json('metadata')->nullable();

            // Timestamps
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
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('payment_transactions');
    }
};
