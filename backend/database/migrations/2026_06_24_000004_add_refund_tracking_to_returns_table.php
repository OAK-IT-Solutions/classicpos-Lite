<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->uuid('refund_payment_id')->nullable()->after('refund_amount');
            $table->timestamp('refunded_at')->nullable()->after('refund_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            $table->dropColumn(['refund_payment_id', 'refunded_at']);
        });
    }
};
