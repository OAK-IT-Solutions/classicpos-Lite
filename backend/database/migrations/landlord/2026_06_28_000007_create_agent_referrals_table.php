<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('agent_referrals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('agent_id');
            $table->uuid('tenant_id')->nullable();

            // Referral link tracking
            $table->string('referral_code', 32);
            $table->string('landing_url')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();

            // Funnel tracking
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('trial_started_at')->nullable();
            $table->timestamp('converted_at')->nullable(); // trial → paid
            $table->timestamp('first_payment_at')->nullable();

            // Commission earned from this referral
            $table->decimal('commission_earned', 12, 2)->default(0);
            $table->boolean('commission_paid')->default(false);

            $table->timestamps();

            $table->foreign('agent_id')->references('id')->on('agents')->onDelete('cascade');
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->index(['agent_id', 'converted_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('agent_referrals');
    }
};
