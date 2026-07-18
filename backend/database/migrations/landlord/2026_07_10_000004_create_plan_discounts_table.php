<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('plan_discounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('name');
            $table->string('code', 50)->nullable()->unique();
            $table->string('type', 20)->default('percentage');
            $table->decimal('value', 10, 2);
            $table->string('billing_cycle', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->integer('max_uses')->nullable();
            $table->integer('current_uses')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('plan_discounts');
    }
};
