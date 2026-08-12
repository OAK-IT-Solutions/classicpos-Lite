<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('efris_fiscal_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->uuid('sale_id')->nullable();
            $table->string('efris_invoice_no', 50)->nullable();
            $table->string('efris_fdn', 50)->nullable();
            $table->text('efris_qr_code')->nullable();
            $table->string('efris_verification_code', 50)->nullable();
            $table->json('request_payload');
            $table->json('response_payload')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('sale_id')->references('id')->on('sales')->nullOnDelete();
            $table->index(['branch_id', 'status', 'created_at']);
            $table->index('sale_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('efris_fiscal_logs');
    }
};
