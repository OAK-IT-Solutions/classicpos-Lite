<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Actor
            $table->string('user_id')->nullable()->uuid();
            $table->enum('user_type', ['admin', 'agent', 'tenant_user', 'system'])->default('system');
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();

            // Action
            $table->string('action'); // tenant.create, subscription.cancel, agent.payout
            $table->string('action_group')->default('general'); // tenant, subscription, agent, ticket, billing, system

            // Subject
            $table->string('subject_type')->nullable(); // Tenant, Subscription, Agent, etc.
            $table->string('subject_id')->nullable()->uuid();
            $table->string('subject_description')->nullable();

            // Context
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->json('metadata')->nullable();

            // Request info
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();
            $table->string('method', 10)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'user_type']);
            $table->index(['subject_type', 'subject_id']);
            $table->index('action_group');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('audit_logs');
    }
};
