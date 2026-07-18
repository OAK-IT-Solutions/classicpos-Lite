<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // 1. Add missing FK on inventory.warehouse_id → warehouses.id
        if ($driver === 'pgsql') {
            $fkExists = collect(DB::select("
                SELECT 1 FROM information_schema.table_constraints
                WHERE constraint_type = 'FOREIGN KEY'
                AND table_name = 'inventory'
                AND constraint_name LIKE '%warehouse%'
            "))->isNotEmpty();

            if (!$fkExists) {
                Schema::table('inventory', function (Blueprint $table) {
                    $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
                });
            }
        } else {
            try {
                Schema::table('inventory', function (Blueprint $table) {
                    $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // FK may already exist
            }
        }

        // 2. Add missing FK indexes for JOIN performance
        $indexes = [
            'grn' => ['purchase_order_id', 'received_by'],
            'grn_items' => ['grn_id', 'product_id'],
            'payments' => ['sale_id'],
            'purchase_order_items' => ['purchase_order_id', 'product_id'],
            'return_items' => ['return_id', 'product_id'],
            'sale_items' => ['sale_id', 'product_id'],
            'stock_transfer_items' => ['stock_transfer_id', 'product_id'],
        ];

        foreach ($indexes as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    if (!Schema::hasColumn($table->getTable(), $column)) {
                        continue;
                    }
                    $indexName = "{$table->getTable()}_{$column}_idx";
                    try {
                        $existingIndexes = Schema::getIndexes($table->getTable());
                        $hasIndex = collect($existingIndexes)->contains(fn ($idx) => in_array($column, $idx['columns']));
                        if (!$hasIndex) {
                            $table->index($column, $indexName);
                        }
                    } catch (\Exception $e) {
                        // Index may already exist
                    }
                }
            });
        }

        if ($driver === 'pgsql') {
            // 3. Fix role_user primary key to include branch_id
            $pkColumns = DB::select("
                SELECT a.attname
                FROM pg_index i
                JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = ANY(i.indkey)
                WHERE i.indrelid = 'role_user'::regclass
                AND i.indisprimary
                ORDER BY a.attnum
            ");

            $currentPkCols = array_map(fn ($c) => $c->attname, $pkColumns);
            $needsPkFix = $currentPkCols !== ['user_id', 'role_id', 'branch_id'];

            if ($needsPkFix) {
                DB::statement('ALTER TABLE role_user DROP CONSTRAINT role_user_pkey');
                DB::statement('ALTER TABLE role_user ALTER COLUMN branch_id SET NOT NULL');

                $uniqueExists = collect(DB::select("
                    SELECT 1 FROM pg_constraint
                    WHERE conname = 'role_user_user_id_role_id_branch_id_unique'
                    AND conrelid = 'role_user'::regclass
                "))->isNotEmpty();

                if ($uniqueExists) {
                    DB::statement('ALTER TABLE role_user DROP CONSTRAINT role_user_user_id_role_id_branch_id_unique');
                }

                DB::statement('ALTER TABLE role_user ADD PRIMARY KEY (user_id, role_id, branch_id)');
            }

            // 4. Add CHECK constraint for sales.status to include 'refunded'
            $statusCheck = collect(DB::select("
                SELECT 1 FROM pg_constraint
                WHERE conname = 'sales_status_check'
                AND conrelid = 'sales'::regclass
            "))->isNotEmpty();

            if ($statusCheck) {
                DB::statement('ALTER TABLE sales DROP CONSTRAINT sales_status_check');
            }

            DB::statement("
                ALTER TABLE sales ADD CONSTRAINT sales_status_check
                CHECK (status IN ('pending_sync', 'synced', 'completed', 'voided', 'payment_failed', 'refunded'))
            ");

            // 5. Fix payments.status — currently an ENUM type, convert to VARCHAR + CHECK
            $isEnum = (bool) DB::select("
                SELECT 1 FROM pg_type
                WHERE typname = 'payments_status_enum'
            ");

            if ($isEnum) {
                DB::statement("ALTER TABLE payments ALTER COLUMN status TYPE VARCHAR(20) USING status::text");
                DB::statement("DROP TYPE IF EXISTS payments_status_enum");
            }

            $paymentsCheck = collect(DB::select("
                SELECT 1 FROM pg_constraint
                WHERE conname = 'payments_status_check'
                AND conrelid = 'payments'::regclass
            "))->isNotEmpty();

            if (!$paymentsCheck) {
                DB::statement("
                    ALTER TABLE payments ADD CONSTRAINT payments_status_check
                    CHECK (status IN ('pending', 'success', 'failed', 'voided', 'refunded'))
                ");
            }

            // 6. Fix payments.method — convert from ENUM to VARCHAR + CHECK
            $methodEnum = (bool) DB::select("
                SELECT 1 FROM pg_type
                WHERE typname = 'payments_method_enum'
            ");

            if ($methodEnum) {
                DB::statement("ALTER TABLE payments ALTER COLUMN method TYPE VARCHAR(20) USING method::text");
                DB::statement("DROP TYPE IF EXISTS payments_method_enum");
            }

            $methodsCheck = collect(DB::select("
                SELECT 1 FROM pg_constraint
                WHERE conname = 'payments_method_check'
                AND conrelid = 'payments'::regclass
            "))->isNotEmpty();

            if (!$methodsCheck) {
                DB::statement("
                    ALTER TABLE payments ADD CONSTRAINT payments_method_check
                    CHECK (method IN ('cash', 'momo', 'card', 'qr', 'transfer'))
                ");
            }
        }
        // SQLite/MySQL: All constraints are handled by Laravel's Schema builder
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }

        // inventory FK
        Schema::table('inventory', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
        });

        // indexes
        $indexes = [
            'grn' => ['purchase_order_id', 'received_by'],
            'grn_items' => ['grn_id', 'product_id'],
            'payments' => ['sale_id'],
            'purchase_order_items' => ['purchase_order_id', 'product_id'],
            'return_items' => ['return_id', 'product_id'],
            'sale_items' => ['sale_id', 'product_id'],
            'stock_transfer_items' => ['stock_transfer_id', 'product_id'],
        ];

        foreach ($indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $indexName = "{$table->getTable()}_{$column}_idx";
                    $table->dropIndex($indexName);
                }
            });
        }

        // role_user PK — revert to (user_id, role_id)
        DB::statement('ALTER TABLE role_user DROP CONSTRAINT role_user_pkey');
        DB::statement('ALTER TABLE role_user ALTER COLUMN branch_id DROP NOT NULL');
        DB::statement('ALTER TABLE role_user ADD PRIMARY KEY (user_id, role_id)');
        DB::statement('ALTER TABLE role_user ADD CONSTRAINT role_user_user_id_role_id_branch_id_unique UNIQUE (user_id, role_id, branch_id)');

        // sales status CHECK
        DB::statement('ALTER TABLE sales DROP CONSTRAINT sales_status_check');
        DB::statement("
            ALTER TABLE sales ADD CONSTRAINT sales_status_check
            CHECK (status IN ('pending_sync', 'synced', 'completed', 'voided', 'payment_failed'))
        ");

        // payments status/method — drop CHECK, restore ENUMs
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_status_check');
        DB::statement('ALTER TABLE payments DROP CONSTRAINT IF EXISTS payments_method_check');
        DB::statement("CREATE TYPE payments_status_enum AS ENUM ('pending', 'success', 'failed', 'voided')");
        DB::statement("ALTER TABLE payments ALTER COLUMN status TYPE payments_status_enum USING status::text::payments_status_enum");
        DB::statement("CREATE TYPE payments_method_enum AS ENUM ('cash', 'momo', 'card', 'qr', 'transfer')");
        DB::statement("ALTER TABLE payments ALTER COLUMN method TYPE payments_method_enum USING method::text::payments_method_enum");
    }
};
