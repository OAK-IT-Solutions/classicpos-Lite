<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('journal_entry_id');
            $table->uuid('account_id');
            $table->decimal('debit_amount', 12, 2)->default(0);
            $table->decimal('credit_amount', 12, 2)->default(0);
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('journal_entry_id')->references('id')->on('journal_entries')->onDelete('cascade');
            $table->foreign('account_id')->references('id')->on('chart_of_accounts');
        });

        DB::statement('ALTER TABLE journal_entry_lines ADD CONSTRAINT jel_check_debit_credit CHECK (debit_amount >= 0 AND credit_amount >= 0 AND (debit_amount = 0 OR credit_amount = 0) AND NOT (debit_amount = 0 AND credit_amount = 0))');
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
