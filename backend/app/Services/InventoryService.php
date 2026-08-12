<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InventoryService
{
    public function updateStock(array $updates, ?string $referenceType = null, ?string $referenceId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($updates, $referenceType, $referenceId, $reason) {
            foreach ($updates as $update) {
                $record = Inventory::where('product_id', $update['product_id'])
                    ->where('warehouse_id', $update['warehouse_id'])
                    ->lockForUpdate()
                    ->first();

                if (!$record) continue;

                $record->increment('quantity', $update['quantity']);
                $record->refresh();

                $this->logMovement($record, $update['quantity'], $referenceType, $referenceId, $reason);
            }
        });
    }

    public function checkStock(string $productId, string $branchId, float $required): bool
    {
        $warehouseIds = Warehouse::where('branch_id', $branchId)->pluck('id');

        if ($warehouseIds->isEmpty()) {
            return false;
        }

        $available = Inventory::where('product_id', $productId)
            ->whereIn('warehouse_id', $warehouseIds)
            ->sum(DB::raw('quantity - reserved_quantity'));

        return $available >= $required;
    }

    public function reserveStock(string $productId, string $branchId, float $quantity): void
    {
        DB::transaction(function () use ($productId, $branchId, $quantity) {
            $warehouseIds = Warehouse::where('branch_id', $branchId)->pluck('id');

            if ($warehouseIds->isEmpty()) {
                throw new \RuntimeException('No warehouse found for branch ' . $branchId);
            }

            $record = Inventory::where('product_id', $productId)
                ->whereIn('warehouse_id', $warehouseIds)
                ->lockForUpdate()
                ->first();

            if (!$record || ($record->quantity - $record->reserved_quantity) < $quantity) {
                throw new \RuntimeException('Insufficient stock available for reservation.');
            }

            $record->increment('reserved_quantity', $quantity);
        });
    }

    public function fulfillReservation(string $productId, string $branchId, float $quantity, ?string $referenceType = null, ?string $referenceId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($productId, $branchId, $quantity, $referenceType, $referenceId, $reason) {
            $warehouseIds = Warehouse::where('branch_id', $branchId)->pluck('id');

            $record = Inventory::where('product_id', $productId)
                ->whereIn('warehouse_id', $warehouseIds)
                ->lockForUpdate()
                ->first();

            if (!$record || $record->reserved_quantity < $quantity) {
                throw new \RuntimeException('Not enough reserved stock to fulfill.');
            }

            $record->decrement('quantity', $quantity);
            $record->decrement('reserved_quantity', $quantity);
            $record->refresh();

            $this->logMovement($record, -$quantity, $referenceType, $referenceId, $reason);
        });
    }

    public function releaseReservation(string $productId, string $branchId, float $quantity): void
    {
        DB::transaction(function () use ($productId, $branchId, $quantity) {
            $warehouseIds = Warehouse::where('branch_id', $branchId)->pluck('id');

            Inventory::where('product_id', $productId)
                ->whereIn('warehouse_id', $warehouseIds)
                ->lockForUpdate()
                ->decrement('reserved_quantity', $quantity);
        });
    }

    public function restock(string $productId, string $branchId, float $quantity, ?string $referenceType = null, ?string $referenceId = null, ?string $reason = null): void
    {
        DB::transaction(function () use ($productId, $branchId, $quantity, $referenceType, $referenceId, $reason) {
            $warehouseIds = Warehouse::where('branch_id', $branchId)->pluck('id');

            if ($warehouseIds->isEmpty()) {
                throw new \RuntimeException('No warehouse found for branch ' . $branchId);
            }

            $record = Inventory::where('product_id', $productId)
                ->whereIn('warehouse_id', $warehouseIds)
                ->lockForUpdate()
                ->first();

            if (!$record) {
                throw new \RuntimeException('No inventory record found for product ' . $productId);
            }

            $record->increment('quantity', $quantity);
            $record->refresh();

            $this->logMovement($record, $quantity, $referenceType, $referenceId, $reason);
        });
    }

    public function reserveItems(array $items): void
    {
        DB::transaction(function () use ($items) {
            foreach ($items as $item) {
                $warehouseIds = Warehouse::where('branch_id', $item['branch_id'])->pluck('id');

                if ($warehouseIds->isEmpty()) {
                    throw new \RuntimeException('No warehouse found for branch ' . $item['branch_id']);
                }

                $record = Inventory::where('product_id', $item['product_id'])
                    ->whereIn('warehouse_id', $warehouseIds)
                    ->lockForUpdate()
                    ->first();

                if (!$record || ($record->quantity - $record->reserved_quantity) < $item['quantity']) {
                    throw new \RuntimeException('Insufficient stock for product ' . $item['product_id']);
                }

                $record->increment('reserved_quantity', $item['quantity']);
            }
        });
    }

    public function logMovement(Inventory $record, float $quantityChange, ?string $referenceType = null, ?string $referenceId = null, ?string $reason = null): void
    {
        StockMovement::create([
            'id' => (string) Str::uuid(),
            'inventory_id' => $record->id,
            'product_id' => $record->product_id,
            'warehouse_id' => $record->warehouse_id,
            'quantity_change' => $quantityChange,
            'running_balance' => $record->quantity,
            'reference_type' => $referenceType ?? 'adjustment',
            'reference_id' => $referenceId,
            'reason' => $reason,
        ]);
    }
}
