<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_reconciliations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('branch_id');
            $table->uuid('operating_account_id');
            $table->date('statement_date');
            $table->decimal('statement_balance', 12, 2);
            $table->decimal('ledger_balance', 12, 2);
            $table->decimal('difference', 12, 2)->default(0);
            $table->string('status', 20)->default('draft'); // draft, in_progress, completed
            $table->timestamp('reconciled_at')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('operating_account_id')->references('id')->on('operating_accounts');
            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['branch_id', 'status']);
        });

        Schema::create('reconciliation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reconciliation_id');
            $table->uuid('journal_entry_id');
            $table->decimal('amount', 12, 2);
            $table->string('type', 30); // cleared, outstanding_deposit, outstanding_check, bank_error, book_error
            $table->boolean('is_cleared')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('reconciliation_id')->references('id')->on('bank_reconciliations')->onDelete('cascade');
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_items');
        Schema::dropIfExists('bank_reconciliations');
    }
};
