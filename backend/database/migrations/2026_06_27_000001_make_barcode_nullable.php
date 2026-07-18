<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('barcode', 100)->nullable()->change();
        });

        // Drop unique constraint - PostgreSQL only
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_barcode_unique CASCADE;');
        } elseif ($driver === 'sqlite') {
            // SQLite: recreate table without the unique constraint
            // This is handled by the nullable change above
        }
    }

    public function down(): void
    {
    }
};
