<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('email');
            $table->string('phone')->nullable();

            // Commission
            $table->decimal('commission_rate', 5, 2)->default(10.00); // percentage
            $table->enum('tier', ['standard', 'silver', 'gold', 'platinum'])->default('standard');
            $table->decimal('tier_threshold', 12, 2)->default(0); // earnings needed for next tier

            // Status
            $table->boolean('is_active')->default(true);
            $table->timestamp('activated_at')->nullable();

            // Referral tracking
            $table->integer('total_referrals')->default(0);
            $table->integer('converted_referrals')->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->decimal('pending_earnings', 12, 2)->default(0);
            $table->decimal('paid_earnings', 12, 2)->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('agents');
    }
};
