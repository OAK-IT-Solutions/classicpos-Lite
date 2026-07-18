<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_register_shifts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('branch_id');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->decimal('opening_balance', 12, 2);
            $table->decimal('cash_sales', 12, 2)->default(0);
            $table->decimal('expected_balance', 12, 2)->default(0);
            $table->decimal('actual_balance', 12, 2)->nullable();
            $table->decimal('variance', 12, 2)->nullable();
            $table->decimal('revenue_to_bank', 12, 2)->default(0);
            $table->string('status', 10)->default('open');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_shifts');
    }
};
