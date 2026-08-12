<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->uuid('branch_id')->nullable()->after('role_id');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });

        $firstBranchId = DB::table('branches')->value('id');
        if ($firstBranchId) {
            DB::table('role_user')->whereNull('branch_id')->update(['branch_id' => $firstBranchId]);
        }

        Schema::table('role_user', function (Blueprint $table) {
            $table->unique(['user_id', 'role_id', 'branch_id']);
        });
    }

    public function down(): void
    {
        Schema::table('role_user', function (Blueprint $table) {
            $table->dropForeign(['branch_id']);
            $table->dropUnique(['user_id', 'role_id', 'branch_id']);
            $table->dropColumn('branch_id');
        });
    }
};
