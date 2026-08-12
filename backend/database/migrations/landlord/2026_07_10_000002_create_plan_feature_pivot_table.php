<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('plan_feature', function (Blueprint $table) {
            $table->uuid('plan_id');
            $table->uuid('feature_id');
            $table->boolean('is_highlighted')->default(false);
            $table->integer('sort_order')->default(0);

            $table->primary(['plan_id', 'feature_id']);
            $table->foreign('plan_id')->references('id')->on('subscription_plans')->onDelete('cascade');
            $table->foreign('feature_id')->references('id')->on('subscription_features')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('plan_feature');
    }
};
