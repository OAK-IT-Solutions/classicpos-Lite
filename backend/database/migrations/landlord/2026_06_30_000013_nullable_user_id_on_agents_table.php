<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('landlord')->table('agents', function (Blueprint $table) {
            $table->uuid('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Cannot reliably revert nullable in PostgreSQL without dropping/recreating
    }
};
