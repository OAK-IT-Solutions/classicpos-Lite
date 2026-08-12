<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoBranchSeeder extends Seeder
{
    public function run(): void
    {
        if (DB::table('branches')->whereIn('name', ['Nairobi HQ - Bar & Grill', 'Mombasa Beach Lounge'])->exists()) {
            return;
        }

        $nairobiId = (string) Str::uuid();
        $mombasaId = (string) Str::uuid();

        DB::table('branches')->insert([
            [
                'id' => $nairobiId,
                'name' => 'Nairobi HQ - Bar & Grill',
                'location' => 'Nairobi, Kenya',
                'timezone' => 'Africa/Nairobi',
                'edge_device_id' => 'EDGE-NBO-001',
                'cloud_sync_status' => 'synced',
                'business_type' => 'bar_restaurant',
                'last_sync_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $mombasaId,
                'name' => 'Mombasa Beach Lounge',
                'location' => 'Mombasa, Kenya',
                'timezone' => 'Africa/Nairobi',
                'edge_device_id' => 'EDGE-MSA-001',
                'cloud_sync_status' => 'synced',
                'business_type' => 'bar_restaurant',
                'last_sync_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('warehouses')->insert([
            [
                'id' => (string) Str::uuid(),
                'branch_id' => $nairobiId,
                'name' => 'Nairobi Main Warehouse',
                'location' => 'Nairobi Industrial Area',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'branch_id' => $mombasaId,
                'name' => 'Mombasa Main Warehouse',
                'location' => 'Mombasa Port Area',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('business_profiles')->insert([
            [
                'id' => (string) Str::uuid(),
                'branch_id' => $nairobiId,
                'legal_business_name' => 'Nairobi HQ - Bar & Grill',
                'trading_name' => 'Nairobi HQ',
                'business_type' => 'bar_restaurant',
                'currency' => 'KES',
                'country' => 'KE',
                'timezone' => 'Africa/Nairobi',
                'location' => 'Nairobi, Kenya',
                'onboarding_completed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'branch_id' => $mombasaId,
                'legal_business_name' => 'Mombasa Beach Lounge',
                'trading_name' => 'Mombasa Beach',
                'business_type' => 'bar_restaurant',
                'currency' => 'KES',
                'country' => 'KE',
                'timezone' => 'Africa/Nairobi',
                'location' => 'Mombasa, Kenya',
                'onboarding_completed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
