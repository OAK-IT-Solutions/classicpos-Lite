<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Make tenant_id nullable to support agent payout transactions
        // which don't belong to any tenant
        DB::connection('landlord')->getSchemaBuilder()->table('payment_transactions', function ($table) {
            $table->uuid('tenant_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::connection('landlord')->getSchemaBuilder()->table('payment_transactions', function ($table) {
            $table->uuid('tenant_id')->nullable(false)->change();
        });
    }
};
