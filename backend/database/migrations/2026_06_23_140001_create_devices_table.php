<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->string('name', 100);
            $table->string('device_id', 255)->unique();
            $table->string('type', 50)->default('edge_node');
            $table->string('status', 20)->default('pending');
            $table->text('description')->nullable();
            $table->string('firmware_version', 50)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->string('os', 100)->nullable();
            $table->string('enrollment_token', 255)->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->json('capabilities')->nullable();
            $table->json('config')->nullable();
            $table->string('certificate_serial', 255)->nullable();
            $table->date('certificate_expires_at')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->index('status');
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
