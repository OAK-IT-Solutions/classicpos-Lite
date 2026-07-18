<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grn_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('grn_id');
            $table->uuid('product_id');
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost', 10, 2);
            $table->string('batch_number', 50)->nullable();
            $table->date('expiry_date')->nullable();
            $table->timestamps();

            $table->foreign('grn_id')->references('id')->on('grn')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grn_items');
    }
};
