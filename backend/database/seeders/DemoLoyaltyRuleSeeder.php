<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoLoyaltyRuleSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('loyalty_rules')->count() > 0) {
            return;
        }

        DB::table('loyalty_rules')->insert([
            'id' => (string) Str::uuid(),
            'points_per_amount' => 10,
            'points_earned' => 1,
            'signup_bonus_points' => 50,
            'member_levels' => json_encode([
                ['level' => 'bronze', 'min_points' => 0],
                ['level' => 'silver', 'min_points' => 100],
                ['level' => 'gold', 'min_points' => 500],
                ['level' => 'platinum', 'min_points' => 2000],
            ]),
            'reward_thresholds' => json_encode([
                ['points' => 100, 'discount' => 5],
                ['points' => 500, 'discount' => 30],
                ['points' => 1000, 'discount' => 75],
            ]),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Demo loyalty rule seeded.');
    }
}
