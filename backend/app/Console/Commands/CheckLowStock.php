<?php

namespace App\Console\Commands;

use App\Events\LowStockDetected;
use App\Models\Inventory;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLowStock extends Command
{
    protected $signature = 'inventory:check-low-stock
        {--branch= : Filter by branch ID}
        {--notify : Send database notifications to admin and inventory_clerk users}';

    protected $description = 'Check inventory for items below minimum stock levels and notify relevant users';

    public function handle(): int
    {
        $query = Inventory::query()
            ->with(['product', 'warehouse.branch'])
            ->whereHas('product', function ($q) {
                $q->where('is_active', true)
                    ->whereColumn('inventory.quantity', '<=', 'products.min_stock');
            });

        if ($branchId = $this->option('branch')) {
            $query->whereHas('warehouse', fn($q) => $q->where('branch_id', $branchId));
        }

        $lowStockItems = $query->get();

        if ($lowStockItems->isEmpty()) {
            $this->info('No low stock items found.');

            return self::SUCCESS;
        }

        $this->warn("Found {$lowStockItems->count()} low stock item(s):");
        $this->newLine();

        $eventItems = [];

        foreach ($lowStockItems as $inventory) {
            $product = $inventory->product;
            $branch = $inventory->warehouse->branch ?? null;
            $branchName = $branch ? $branch->name : 'Unknown';

            $message = "Low stock: {$product->name} (qty: {$inventory->quantity}, min: {$product->min_stock}) in {$branchName}";

            $this->line("  - {$message}");

            Log::warning('Low stock alert', [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'warehouse_id' => $inventory->warehouse_id,
                'branch_id' => $branch?->id,
                'current_qty' => $inventory->quantity,
                'min_stock' => $product->min_stock,
            ]);

            $eventItems[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'branch_id' => $branch?->id ?? '',
                'branch_name' => $branchName,
                'current_stock' => $inventory->quantity,
                'min_stock' => $product->min_stock,
            ];
        }

        if ($this->option('notify') && !empty($eventItems)) {
            LowStockDetected::dispatch($eventItems);
        }

        $this->newLine();
        $this->info('Low stock check complete.');

        return self::SUCCESS;
    }
}
