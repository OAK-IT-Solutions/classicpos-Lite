<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('tenant_subscriptions', function (Blueprint $table) {
            $table->uuid('client_user_id')->nullable()->after('tenant_id');
            $table->foreign('client_user_id')->references('id')->on('client_users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('tenant_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['client_user_id']);
            $table->dropColumn('client_user_id');
        });
    }
};
