<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desktop_licenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('business_name', 255);
            $table->string('email', 255);
            $table->string('license_key', 50)->unique();
            $table->enum('plan', ['professional', 'enterprise'])->default('professional');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('USD');
            $table->string('payment_method', 50);
            $table->string('payment_reference', 255)->nullable();
            $table->enum('status', ['pending', 'active', 'expired', 'voided'])->default('pending');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('device_fingerprint', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('status');
            $table->index('plan');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_licenses');
    }
};
