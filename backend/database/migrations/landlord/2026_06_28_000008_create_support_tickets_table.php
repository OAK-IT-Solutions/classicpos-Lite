<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('support_tickets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('user_id')->nullable(); // tenant user who created it

            // Ticket details
            $table->string('subject');
            $table->text('description');
            $table->string('ticket_number', 32)->unique(); // e.g., TKT-0001

            // Status & priority
            $table->enum('status', ['open', 'in_progress', 'waiting_reply', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->enum('category', ['billing', 'technical', 'feature_request', 'general', 'bug_report'])->default('general');

            // Assignment
            $table->uuid('assigned_to')->nullable(); // admin user ID
            $table->timestamp('assigned_at')->nullable();

            // SLA tracking
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Message counts
            $table->integer('message_count')->default(0);
            $table->integer('unread_count')->default(0);

            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'priority']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('support_tickets');
    }
};
