<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoStockTransferSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('stock_transfers')->exists()) {
            return;
        }

        $warehouses = DB::table('warehouses')->take(2)->pluck('id');
        if ($warehouses->count() < 2) {
            return;
        }

        $products = DB::table('products')->take(3)->pluck('id');
        if ($products->isEmpty()) {
            return;
        }

        $fromId = $warehouses[0];
        $toId = $warehouses[1];
        $transferId = (string) Str::uuid();

        DB::table('stock_transfers')->insert([
            'id' => $transferId,
            'from_warehouse_id' => $fromId,
            'to_warehouse_id' => $toId,
            'status' => 'completed',
            'notes' => 'Initial stock replenishment',
            'transferred_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($products as $productId) {
            DB::table('stock_transfer_items')->insert([
                'id' => (string) Str::uuid(),
                'stock_transfer_id' => $transferId,
                'product_id' => $productId,
                'quantity' => 10,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command?->info('Demo stock transfer created.');
    }
}
