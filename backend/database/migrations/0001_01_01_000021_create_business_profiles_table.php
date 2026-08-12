<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id')->unique();
            $table->string('legal_business_name', 255);
            $table->string('trading_name', 255)->nullable();
            $table->enum('business_type', ['bar_restaurant', 'retail', 'service', 'pharmacy'])->default('bar_restaurant');
            $table->string('tax_id', 50)->nullable();
            $table->boolean('vat_registered')->default(false);
            $table->string('currency', 3)->default('USD');
            $table->string('country', 100);
            $table->string('timezone', 50)->default('UTC');
            $table->string('address_line1', 255)->nullable();
            $table->string('address_line2', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state_province', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('logo_url', 255)->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->integer('established_year')->nullable();
            $table->text('description')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('onboarding_completed')->default(false);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
