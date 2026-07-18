<?php

namespace Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('syncs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->string('table_name', 50);
            $table->string('record_id', 50);
            $table->enum('action', ['create', 'update', 'delete']);
            $table->jsonb('payload')->nullable();
            $table->enum('status', ['pending', 'synced', 'failed'])->default('pending');
            $table->jsonb('conflict_data')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('syncs');
    }
};
