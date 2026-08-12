<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->decimal('discount_percent_yearly', 5, 2)->nullable()->after('price_yearly');
            $table->boolean('is_popular')->default(false)->after('sort_order');
            $table->string('highlight_color', 20)->nullable()->after('is_popular');
            $table->string('cta_text', 100)->nullable()->after('highlight_color');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->table('subscription_plans', function (Blueprint $table) {
            $table->dropColumn(['discount_percent_yearly', 'is_popular', 'highlight_color', 'cta_text']);
        });
    }
};
