<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Offline seeder — runs only tenant-level seeders.
 * Skips LandlordSeeder and OakItSeeder (which require landlord PostgreSQL DB).
 */
class OfflineDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            RolePermissionSeeder::class,
            DemoBranchSeeder::class,
            DemoProductSeeder::class,
            DemoSubscriptionSeeder::class,
            DemoUserSeeder::class,
            DefaultAccountSeeder::class,
            DemoPromotionSeeder::class,
            DemoTaxProfileSeeder::class,
            DemoLoyaltyRuleSeeder::class,
            DemoStockTransferSeeder::class,
            DemoReturnSeeder::class,
            CategorySeeder::class,
            ChartOfAccountSeeder::class,
            // OakItSeeder skipped — requires landlord PostgreSQL DB
        ]);
    }
}
