<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DefaultAccountSeeder extends Seeder
{
    public function run(): void
    {
        $emails = ['branch_manager@classicpos.app', 'inventory_clerk@classicpos.app'];

        $existingUsers = DB::table('users')->whereIn('email', $emails)->get();
        if ($existingUsers->isNotEmpty()) {
            $allHaveRoles = $existingUsers->every(fn($u) => DB::table('role_user')->where('user_id', $u->id)->exists());
            if ($allHaveRoles) { return; }
        }

        $branchManagerRoleId = DB::table('roles')->where('name', 'branch_manager')->value('id');
        $inventoryClerkRoleId = DB::table('roles')->where('name', 'inventory_clerk')->value('id');
        $firstBranch = DB::table('branches')->first();

        if (!$branchManagerRoleId || !$inventoryClerkRoleId || !$firstBranch) {
            return;
        }

        $branchManagerId = (string) Str::uuid();
        $inventoryClerkId = (string) Str::uuid();

        DB::table('users')->insert([
            [
                'id' => $branchManagerId,
                'name' => 'Branch Manager',
                'email' => 'branch_manager@classicpos.app',
                'password' => Hash::make(Str::random(40)),
                'branch_id' => $firstBranch->id,
                'is_active' => false,
                'is_protected' => true,
                'is_default_account' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $inventoryClerkId,
                'name' => 'Inventory Clerk',
                'email' => 'inventory_clerk@classicpos.app',
                'password' => Hash::make(Str::random(40)),
                'branch_id' => $firstBranch->id,
                'is_active' => false,
                'is_protected' => true,
                'is_default_account' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('role_user')->insert([
            [
                'user_id' => $branchManagerId,
                'role_id' => $branchManagerRoleId,
                'branch_id' => $firstBranch->id,
            ],
            [
                'user_id' => $inventoryClerkId,
                'role_id' => $inventoryClerkRoleId,
                'branch_id' => $firstBranch->id,
            ],
        ]);

        DB::table('branch_user')->insert([
            ['user_id' => $branchManagerId, 'branch_id' => $firstBranch->id, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $inventoryClerkId, 'branch_id' => $firstBranch->id, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
