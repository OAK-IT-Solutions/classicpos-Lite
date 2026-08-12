<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->string('condition', 20)->default('returnable')->after('reason');
        });

        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->uuid('product_id');
            $table->uuid('warehouse_id');
            $table->decimal('quantity', 12, 2);
            $table->string('type', 30); // 'damaged', 'defect', 'expired', 'stolen', 'write_off', 'correction'
            $table->string('reason', 500);
            $table->string('reference', 255)->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('product_id')->references('id')->on('products');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->index(['branch_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropColumn('condition');
        });
    }
};
