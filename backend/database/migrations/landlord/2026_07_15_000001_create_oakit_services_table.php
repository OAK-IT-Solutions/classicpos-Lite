<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('oakit_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('features')->nullable();
            $table->json('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::connection('landlord')->create('oakit_plan_services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->uuid('service_id');
            $table->boolean('is_included')->default(true);
            $table->integer('custom_limit')->nullable();
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
            $table->foreign('service_id')->references('id')->on('oakit_services')->onDelete('cascade');
            $table->unique(['plan_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('oakit_plan_services');
        Schema::connection('landlord')->dropIfExists('oakit_services');
    }
};
