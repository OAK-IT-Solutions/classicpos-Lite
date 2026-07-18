<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->decimal('reserved_quantity', 12, 3)->default(0)->change();
        });

        if (!Schema::hasColumn('inventory', 'sync_status')) {
            Schema::table('inventory', function (Blueprint $table) {
                $table->string('sync_status', 20)->default('synced');
            });
        }
    }

    public function down(): void
    {
        Schema::table('inventory', function (Blueprint $table) {
            $table->decimal('reserved_quantity', 12, 2)->default(0)->change();
        });

        if (Schema::hasColumn('inventory', 'sync_status')) {
            Schema::table('inventory', function (Blueprint $table) {
                $table->dropColumn('sync_status');
            });
        }
    }
};
