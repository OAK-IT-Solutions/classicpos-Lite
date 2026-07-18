<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('currencies', function (Blueprint $table) {
            $table->string('code', 5)->primary();
            $table->string('name', 100);
            $table->string('symbol', 10);
            $table->decimal('exchange_rate_to_usd', 14, 6)->default(1);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->integer('decimal_places')->default(2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('currencies');
    }
};
