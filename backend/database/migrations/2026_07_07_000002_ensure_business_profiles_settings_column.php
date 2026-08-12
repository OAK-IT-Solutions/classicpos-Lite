<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('business_profiles')) {
            return;
        }

        // The settings JSON column may not exist on older installs - add it if missing
        if (!Schema::hasColumn('business_profiles', 'settings')) {
            Schema::table('business_profiles', function (Blueprint $table) {
                $table->json('settings')->nullable()->after('logo_url');
            });
        }
    }

    public function down(): void
    {
        // We don't drop settings to preserve data
    }
};
