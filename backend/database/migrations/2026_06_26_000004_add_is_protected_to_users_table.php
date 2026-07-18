<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_protected')->default(false)->after('is_active');
        });

        $adminUserIds = DB::table('role_user')
            ->join('roles', 'role_user.role_id', '=', 'roles.id')
            ->where('roles.name', 'admin')
            ->distinct()
            ->pluck('role_user.user_id');

        if ($adminUserIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('id', $adminUserIds)
                ->update(['is_protected' => true]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_protected');
        });
    }
};
