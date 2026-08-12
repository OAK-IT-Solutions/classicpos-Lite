<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->create('ticket_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ticket_id');

            // Sender
            $table->enum('sender_type', ['tenant_user', 'admin', 'system'])->default('tenant_user');
            $table->string('sender_id')->nullable()->uuid(); // user_id or admin_id
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();

            // Message
            $table->text('message');
            $table->json('attachments')->nullable();

            // Read status
            $table->boolean('is_read')->default(false);
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->foreign('ticket_id')->references('id')->on('support_tickets')->onDelete('cascade');
            $table->index(['ticket_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('landlord')->dropIfExists('ticket_messages');
    }
};
