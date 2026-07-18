<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('agent_commissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->uuid('tenant_id')->nullable();
            $table->uuid('subscription_id')->nullable();
            $table->uuid('payment_transaction_id')->nullable();

            // Commission details
            $table->decimal('amount', 12, 2);
            $table->decimal('rate', 5, 2); // commission rate at time of earning
            $table->string('type')->default('subscription_referral'); // subscription_referral, sale, custom

            // Status
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
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('agent_commissions');
    }
};
