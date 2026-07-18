<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoTaxProfileSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('tax_profiles')->count() > 0) {
            return;
        }

        $profiles = [
            [
                'name' => 'VAT 16%',
                'rate' => 16,
                'type' => 'exclusive',
                'is_default' => true,
                'description' => 'Standard VAT at 16% (exclusive)',
            ],
            [
                'name' => 'VAT 8%',
                'rate' => 8,
                'type' => 'exclusive',
                'is_default' => false,
                'description' => 'Reduced VAT at 8% (exclusive)',
            ],
            [
                'name' => 'Sales Tax 5%',
                'rate' => 5,
                'type' => 'inclusive',
                'is_default' => false,
                'description' => 'Sales tax included in price',
            ],
        ];

        foreach ($profiles as $profile) {
            DB::table('tax_profiles')->insert([
                'id' => (string) Str::uuid(),
                'name' => $profile['name'],
                'rate' => $profile['rate'],
                'type' => $profile['type'],
                'is_default' => $profile['is_default'],
                'is_active' => true,
                'description' => $profile['description'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Demo tax profiles seeded.');
    }
}
