<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->uuid('customer_id')->nullable();
            $table->string('invoice_number', 50)->unique();
            $table->decimal('total_amount', 12, 2);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->enum('payment_method', ['cash', 'mobile_money', 'card', 'qr']);
            $table->enum('status', ['pending_sync', 'synced', 'completed', 'voided', 'payment_failed'])->default('pending_sync');
            $table->enum('sync_status', ['pending', 'synced', 'conflict'])->default('pending');
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
