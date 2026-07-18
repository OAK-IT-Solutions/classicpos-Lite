<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->decimal('points_per_amount', 12, 2)->default(10.00);
            $table->integer('points_earned')->default(1);
            $table->integer('signup_bonus_points')->default(0);
            $table->json('member_levels')->nullable();
            $table->json('reward_thresholds')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_rules');
    }
};
