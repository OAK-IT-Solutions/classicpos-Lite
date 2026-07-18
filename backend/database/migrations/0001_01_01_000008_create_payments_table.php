<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('sale_id');
            $table->decimal('amount', 12, 2);
            $table->enum('method', ['cash', 'momo', 'card', 'qr', 'transfer']);
            $table->string('gateway', 50)->nullable();
            $table->string('txn_id', 100)->nullable();
            $table->enum('status', ['pending', 'success', 'failed', 'voided'])->default('pending');
            $table->timestamps();

            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
