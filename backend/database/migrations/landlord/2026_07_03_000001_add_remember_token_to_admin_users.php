<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('admin_users', function (Blueprint $table) {
            if (!Schema::connection('landlord')->hasColumn('admin_users', 'remember_token')) {
                $table->rememberToken()->after('last_login_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('admin_users', function (Blueprint $table) {
            $table->dropColumn('remember_token');
        });
    }
};
