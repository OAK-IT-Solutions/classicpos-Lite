<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('products')->count() > 0) {
            return;
        }

        $categories = ['Beer', 'Spirits', 'Whisky', 'Liqueur', 'Soft Drinks', 'Food', 'Snacks'];
        $categoryIds = [];
        foreach ($categories as $catName) {
            $existing = DB::table('categories')->where('name', $catName)->value('id');
            if ($existing) {
                $categoryIds[$catName] = $existing;
            } else {
                $uuid = (string) Str::uuid();
                DB::table('categories')->insert([
                    'id' => $uuid,
                    'name' => $catName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $categoryIds[$catName] = $uuid;
            }
        }

        $products = [
            // Beers
            ['name' => 'Tusker Lager 500ml', 'barcode' => 'BR001', 'category' => 'Beer', 'price' => 3.00, 'cost' => 1.80, 'stock_uom' => 'pcs', 'min_stock' => 50],
            ['name' => 'White Cap Lager 500ml', 'barcode' => 'BR002', 'category' => 'Beer', 'price' => 3.00, 'cost' => 1.80, 'stock_uom' => 'pcs', 'min_stock' => 50],
            ['name' => 'Guinness Draught 500ml', 'barcode' => 'BR003', 'category' => 'Beer', 'price' => 4.00, 'cost' => 2.50, 'stock_uom' => 'pcs', 'min_stock' => 30],
            ['name' => 'Heineken 500ml', 'barcode' => 'BR004', 'category' => 'Beer', 'price' => 4.00, 'cost' => 2.50, 'stock_uom' => 'pcs', 'min_stock' => 30],
            // Spirits
            ['name' => 'Smirnoff Vodka 750ml', 'barcode' => 'BR005', 'category' => 'Spirits', 'price' => 15.00, 'cost' => 9.00, 'stock_uom' => 'pcs', 'min_stock' => 10],
            ['name' => 'Johnnie Walker Red 750ml', 'barcode' => 'BR006', 'category' => 'Whisky', 'price' => 25.00, 'cost' => 16.00, 'stock_uom' => 'pcs', 'min_stock' => 5],
            ['name' => 'Jameson Irish 750ml', 'barcode' => 'BR007', 'category' => 'Whisky', 'price' => 22.00, 'cost' => 14.00, 'stock_uom' => 'pcs', 'min_stock' => 5],
            ['name' => 'Baileys Irish Cream 750ml', 'barcode' => 'BR008', 'category' => 'Liqueur', 'price' => 28.00, 'cost' => 18.00, 'stock_uom' => 'pcs', 'min_stock' => 5],
            // Soft Drinks
            ['name' => 'Coca Cola 330ml', 'barcode' => 'BR009', 'category' => 'Soft Drinks', 'price' => 1.50, 'cost' => 0.70, 'stock_uom' => 'pcs', 'min_stock' => 100],
            ['name' => 'Fanta Orange 330ml', 'barcode' => 'BR010', 'category' => 'Soft Drinks', 'price' => 1.50, 'cost' => 0.70, 'stock_uom' => 'pcs', 'min_stock' => 80],
            ['name' => 'Sprite 330ml', 'barcode' => 'BR011', 'category' => 'Soft Drinks', 'price' => 1.50, 'cost' => 0.70, 'stock_uom' => 'pcs', 'min_stock' => 80],
            ['name' => 'Mineral Water 500ml', 'barcode' => 'BR012', 'category' => 'Soft Drinks', 'price' => 1.00, 'cost' => 0.40, 'stock_uom' => 'pcs', 'min_stock' => 100],
            ['name' => 'Fresh Orange Juice 300ml', 'barcode' => 'BR013', 'category' => 'Soft Drinks', 'price' => 2.50, 'cost' => 1.20, 'stock_uom' => 'pcs', 'min_stock' => 30],
            // Food
            ['name' => 'Nyama Choma (1kg)', 'barcode' => 'BR014', 'category' => 'Food', 'price' => 12.00, 'cost' => 7.00, 'stock_uom' => 'kg', 'min_stock' => 10],
            ['name' => 'Grilled Tilapia', 'barcode' => 'BR015', 'category' => 'Food', 'price' => 8.00, 'cost' => 4.50, 'stock_uom' => 'pcs', 'min_stock' => 10],
            ['name' => 'Beef Steak (300g)', 'barcode' => 'BR016', 'category' => 'Food', 'price' => 10.00, 'cost' => 6.00, 'stock_uom' => 'pcs', 'min_stock' => 15],
            ['name' => 'Chips/Fries Portion', 'barcode' => 'BR017', 'category' => 'Food', 'price' => 4.00, 'cost' => 1.50, 'stock_uom' => 'pcs', 'min_stock' => 40],
            ['name' => 'Chicken Wings (6pcs)', 'barcode' => 'BR018', 'category' => 'Food', 'price' => 7.00, 'cost' => 4.00, 'stock_uom' => 'pcs', 'min_stock' => 20],
            ['name' => 'Mushroom Burger', 'barcode' => 'BR019', 'category' => 'Food', 'price' => 6.00, 'cost' => 3.50, 'stock_uom' => 'pcs', 'min_stock' => 20],
            // Snacks
            ['name' => 'Chicken Samosa (Pc)', 'barcode' => 'BR020', 'category' => 'Snacks', 'price' => 1.00, 'cost' => 0.40, 'stock_uom' => 'pcs', 'min_stock' => 50],
            ['name' => 'Spring Rolls (3pcs)', 'barcode' => 'BR021', 'category' => 'Snacks', 'price' => 3.00, 'cost' => 1.50, 'stock_uom' => 'pcs', 'min_stock' => 30],
            ['name' => 'Peanuts Roasted (100g)', 'barcode' => 'BR022', 'category' => 'Snacks', 'price' => 1.50, 'cost' => 0.60, 'stock_uom' => 'pcs', 'min_stock' => 40],
            ['name' => 'Plantain Crisps (100g)', 'barcode' => 'BR023', 'category' => 'Snacks', 'price' => 1.50, 'cost' => 0.60, 'stock_uom' => 'pcs', 'min_stock' => 40],
        ];

        $warehouseIds = DB::table('warehouses')->pluck('id')->toArray();

        $productIds = [];
        foreach ($products as $product) {
            $id = (string) Str::uuid();
            DB::table('products')->insert([
                'id' => $id,
                'name' => $product['name'],
                'barcode' => $product['barcode'],
                'category_id' => $categoryIds[$product['category']],
                'price' => $product['price'],
                'cost' => $product['cost'],
                'stock_uom' => $product['stock_uom'],
                'min_stock' => $product['min_stock'],
                'description' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $productIds[] = $id;
        }

        foreach ($productIds as $productId) {
            foreach ($warehouseIds as $warehouseId) {
                DB::table('inventory')->insert([
                    'id' => (string) Str::uuid(),
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => rand(15, 100),
                    'batch_number' => 'BATCH-' . strtoupper(Str::random(6)),
                    'expiry_date' => now()->addMonths(rand(3, 18)),
                    'serial_number' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
