<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->change();
        });
        Schema::connection('landlord')->table('support_tickets', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->change();
            $table->uuid('client_user_id')->nullable()->after('tenant_id');
        });
        Schema::connection('landlord')->table('payment_transactions', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable()->change();
            $table->uuid('client_user_id')->nullable()->after('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable(false)->change();
        });
        Schema::connection('landlord')->table('support_tickets', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable(false)->change();
            $table->dropColumn('client_user_id');
        });
        Schema::connection('landlord')->table('payment_transactions', function (Blueprint $table) {
            $table->uuid('tenant_id')->nullable(false)->change();
            $table->dropColumn('client_user_id');
        });
    }
};
