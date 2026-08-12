<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('tenant_subscriptions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->after('billing_cycle');
            $table->decimal('original_amount', 10, 2)->nullable()->after('amount');
            $table->uuid('discount_id')->nullable()->after('original_amount');
            $table->decimal('discount_percent', 5, 2)->nullable()->after('discount_id');

            $table->foreign('discount_id')->references('id')->on('plan_discounts')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['discount_id']);
            $table->dropColumn(['amount', 'original_amount', 'discount_id', 'discount_percent']);
        });
    }
};
