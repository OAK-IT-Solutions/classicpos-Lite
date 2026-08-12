<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoPromotionSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('promotions')->count() > 0) {
            return;
        }

        $promotions = [
            [
                'code' => 'WELCOME10',
                'type' => 'percentage',
                'value' => 10,
                'min_order_amount' => 20,
                'max_discount_amount' => 50,
                'usage_limit' => 1000,
                'description' => '10% off your first order (max $50 discount)',
            ],
            [
                'code' => 'FLAT5',
                'type' => 'flat',
                'value' => 5,
                'min_order_amount' => 15,
                'max_discount_amount' => null,
                'usage_limit' => 500,
                'description' => '$5 off orders over $15',
            ],
            [
                'code' => 'HAPPYHOUR',
                'type' => 'percentage',
                'value' => 15,
                'min_order_amount' => 10,
                'max_discount_amount' => 30,
                'usage_limit' => 200,
                'description' => 'Happy hour special — 15% off (max $30)',
            ],
        ];

        foreach ($promotions as $promo) {
            DB::table('promotions')->insert([
                'id' => (string) Str::uuid(),
                'code' => $promo['code'],
                'type' => $promo['type'],
                'value' => $promo['value'],
                'min_order_amount' => $promo['min_order_amount'],
                'max_discount_amount' => $promo['max_discount_amount'],
                'usage_limit' => $promo['usage_limit'],
                'used_count' => 0,
                'valid_from' => now()->subMonth(),
                'valid_until' => now()->addYear(),
                'is_active' => true,
                'description' => $promo['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Demo promotions seeded.');
    }
}
