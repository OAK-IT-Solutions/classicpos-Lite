<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoReturnSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('returns')->exists()) {
            return;
        }

        $branch = DB::table('branches')->first();
        if (!$branch) {
            $branchId = (string) Str::uuid();
            DB::table('branches')->insert([
                'id' => $branchId,
                'name' => 'Demo Branch',
                'location' => 'Demo Location',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $branch = (object) ['id' => $branchId];
        }

        $products = DB::table('products')->take(2)->pluck('id');
        if ($products->isEmpty()) {
            foreach (['Widget A', 'Widget B'] as $name) {
                $productId = (string) Str::uuid();
                DB::table('products')->insert([
                    'id' => $productId,
                    'name' => $name,
                    'barcode' => 'DEMO' . strtoupper(Str::random(6)),
                    'price' => 10.00,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $products->push($productId);
            }
        }

        $sale = DB::table('sales')->first();
        if (!$sale) {
            $saleId = (string) Str::uuid();
            DB::table('sales')->insert([
                'id' => $saleId,
                'branch_id' => $branch->id,
                'invoice_number' => 'INV-DEMO-' . strtoupper(Str::random(8)),
                'total_amount' => 20.00,
                'payment_method' => 'cash',
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ($products as $productId) {
                DB::table('sale_items')->insert([
                    'id' => (string) Str::uuid(),
                    'sale_id' => $saleId,
                    'product_id' => $productId,
                    'quantity' => 1,
                    'price' => 10.00,
                    'subtotal' => 10.00,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            $sale = (object) ['id' => $saleId];
        }

        $returnId = (string) Str::uuid();

        DB::table('returns')->insert([
            'id' => $returnId,
            'sale_id' => $sale->id,
            'branch_id' => $branch->id,
            'reason' => 'Customer returned damaged items',
            'status' => 'pending',
            'refund_amount' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ($products as $productId) {
            DB::table('return_items')->insert([
                'id' => (string) Str::uuid(),
                'return_id' => $returnId,
                'product_id' => $productId,
                'quantity' => 1,
                'reason' => 'Damaged on arrival',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command?->info('Demo return created.');
    }
}
