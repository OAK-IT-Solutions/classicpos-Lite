<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoSubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        if (!Schema::hasColumn('subscriptions', 'branch_id')) {
            return;
        }

        if (DB::table('subscriptions')->count() > 0) {
            return;
        }

        $branches = DB::table('branches')->get();

        foreach ($branches as $i => $branch) {
            $planType = $i === 0 ? 'standard' : 'standard';
            DB::table('subscriptions')->insert([
                'id' => (string) Str::uuid(),
                'branch_id' => $branch->id,
                'plan_type' => $planType,
                'billing_cycle' => 'monthly',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(30),
                'starts_at' => now(),
                'ends_at' => null,
                'cancelled_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
