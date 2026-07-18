<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('efris_fdn', 50)->nullable()->after('status');
            $table->text('efris_qr_code')->nullable()->after('efris_fdn');
            $table->string('efris_verification_code', 50)->nullable()->after('efris_qr_code');
            $table->string('efris_fiscal_status', 20)->nullable()->after('efris_verification_code');
            $table->index('efris_fiscal_status');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['efris_fdn', 'efris_qr_code', 'efris_verification_code', 'efris_fiscal_status']);
        });
    }
};
