<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('efris_configs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('integration_id');
            $table->uuid('branch_id');
            $table->string('tin', 20);
            $table->string('weaf_email', 200);
            $table->text('weaf_token')->nullable();
            $table->timestamp('weaf_token_expires_at')->nullable();
            $table->string('weaf_environment', 20)->default('sandbox');
            $table->string('company_name', 200)->nullable();
            $table->integer('company_weaf_id')->nullable();
            $table->boolean('auto_fiscalize')->default(true);
            $table->boolean('fiscalize_receipts')->default(true);
            $table->timestamps();

            $table->foreign('integration_id')->references('id')->on('integrations')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->unique('integration_id');
            $table->index('branch_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('efris_configs');
    }
};
