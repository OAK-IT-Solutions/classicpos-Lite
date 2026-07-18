<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('returnable')->default(false)->after('name');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('returnable')->nullable()->default(null)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('returnable');
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('returnable');
        });
    }
};
