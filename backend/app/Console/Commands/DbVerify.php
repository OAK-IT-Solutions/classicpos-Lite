<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DbVerify extends Command
{
    protected $signature = 'db:verify';

    protected $description = 'Verify database integrity — checks key tables, columns, and consistency';

    public function handle(): int
    {
        $this->info('Verifying database integrity...');
        $errors = [];

        // ---- Check main (pgsql) connection ----
        $this->line('  Checking main database (pgsql)...');

        $requiredTables = [
            'users' => ['id', 'name', 'email', 'password', 'branch_id'],
            'branches' => ['id', 'name'],
            'products' => ['id', 'name', 'price'],
            'sales' => ['id', 'invoice_number', 'total_amount'],
            'inventory' => ['id', 'product_id', 'warehouse_id', 'quantity'],
            'payments' => ['id', 'sale_id', 'amount', 'status'],
            'stock_movements' => ['id', 'product_id', 'quantity_change', 'reference_type'],
            'categories' => ['id', 'name'],
            'customers' => ['id', 'name', 'email'],
            'warehouses' => ['id', 'branch_id', 'name'],
            'role_user' => ['role_id', 'user_id', 'branch_id'],
            'permissions' => ['id', 'name'],
            'permission_role' => ['permission_id', 'role_id'],
            'personal_access_tokens' => ['id', 'tokenable_type', 'tokenable_id', 'token'],
        ];

        foreach ($requiredTables as $table => $columns) {
            if (!Schema::hasTable($table)) {
                $errors[] = "Missing table: {$table}";
                continue;
            }
            foreach ($columns as $column) {
                if (!Schema::hasColumn($table, $column)) {
                    $errors[] = "Missing column {$table}.{$column}";
                }
            }
        }

        // ---- Check landlord connection ----
        $this->line('  Checking landlord database...');

        $landlordTables = [
            'tenants' => ['id', 'name', 'slug', 'status'],
            'admin_users' => ['id', 'name', 'email', 'password', 'role'],
            'subscription_plans' => ['id', 'name', 'slug', 'price_monthly'],
            'personal_access_tokens' => ['id', 'tokenable_type', 'tokenable_id', 'token'],
        ];

        try {
            foreach ($landlordTables as $table => $columns) {
                if (!Schema::connection('landlord')->hasTable($table)) {
                    $errors[] = "Missing landlord table: {$table}";
                    continue;
                }
                foreach ($columns as $column) {
                    if (!Schema::connection('landlord')->hasColumn($table, $column)) {
                        $errors[] = "Missing landlord column {$table}.{$column}";
                    }
                }
            }
        } catch (\Exception $e) {
            $errors[] = 'Cannot connect to landlord database: ' . $e->getMessage();
        }

        // ---- Check data consistency ----
        $this->line('  Checking data consistency...');

        try {
            // Verify migration state
            $migrationsRun = DB::table('migrations')->count();
            $this->line("    Migrations run: {$migrationsRun}");

            // Check for orphaned records
            $orphanedInventory = DB::table('inventory')
                ->leftJoin('products', 'inventory.product_id', '=', 'products.id')
                ->whereNull('products.id')
                ->count();
            if ($orphanedInventory > 0) {
                $errors[] = "Found {$orphanedInventory} orphaned inventory records (no matching product)";
            }

            $orphanedSales = DB::table('sales')
                ->leftJoin('branches', 'sales.branch_id', '=', 'branches.id')
                ->whereNull('branches.id')
                ->count();
            if ($orphanedSales > 0) {
                $errors[] = "Found {$orphanedSales} orphaned sales records (no matching branch)";
            }
        } catch (\Exception $e) {
            $errors[] = 'Consistency check error: ' . $e->getMessage();
        }

        // ---- Report ----
        if (empty($errors)) {
            $this->info('  OK — All checks passed.');
            return Command::SUCCESS;
        }

        $this->error('  FAILED — ' . count($errors) . ' issue(s) found:');
        foreach ($errors as $error) {
            $this->line("    - {$error}");
        }

        return Command::FAILURE;
    }
}
