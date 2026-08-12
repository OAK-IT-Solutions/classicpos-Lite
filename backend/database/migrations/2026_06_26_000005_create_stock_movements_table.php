<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('inventory_id');
            $table->uuid('product_id');
            $table->uuid('warehouse_id');
            $table->decimal('quantity_change', 12, 2);
            $table->decimal('running_balance', 12, 2);
            $table->string('reference_type', 50);
            $table->uuid('reference_id')->nullable();
            $table->string('reason', 500)->nullable();
            $table->timestamps();

            $table->foreign('inventory_id')->references('id')->on('inventory')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->index(['inventory_id', 'created_at']);
            $table->index(['product_id', 'warehouse_id']);
            $table->index('reference_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
