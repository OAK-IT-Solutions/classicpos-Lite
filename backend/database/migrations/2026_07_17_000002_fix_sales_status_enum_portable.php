<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // PostgreSQL: Convert enum to VARCHAR
            DB::statement("ALTER TABLE sales ALTER COLUMN status TYPE VARCHAR(20)");
            DB::statement("DROP TYPE IF EXISTS sales_status_enum");
        }
        // SQLite: no action needed — enums are stored as VARCHAR with CHECK constraints
        // MySQL: no action needed — enums are stored as VARCHAR
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE sales ALTER COLUMN status DROP DEFAULT");
            DB::statement("ALTER TABLE sales ALTER COLUMN status TYPE VARCHAR(20)");
            DB::statement("DROP TYPE IF EXISTS sales_status_enum");
            DB::statement("CREATE TYPE sales_status_enum AS ENUM ('pending_sync', 'synced', 'completed', 'voided', 'payment_failed')");
            DB::statement("ALTER TABLE sales ALTER COLUMN status TYPE sales_status_enum USING status::text::sales_status_enum");
            DB::statement("ALTER TABLE sales ALTER COLUMN status SET DEFAULT 'pending_sync'");
        }
    }
};
