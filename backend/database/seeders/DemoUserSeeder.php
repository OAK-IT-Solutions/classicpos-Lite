<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $existingUsers = DB::table('users')->whereIn('email', ['admin@classicpos.app', 'cashier@classicpos.app'])->get();
        if ($existingUsers->isNotEmpty()) {
            $allHaveRoles = $existingUsers->every(fn($u) => DB::table('role_user')->where('user_id', $u->id)->exists());
            if ($allHaveRoles) { return; }
        }

        $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
        $cashierRoleId = DB::table('roles')->where('name', 'cashier')->value('id');
        $nairobiBranchId = DB::table('branches')->where('name', 'Nairobi HQ - Bar & Grill')->value('id');

        $adminId = (string) Str::uuid();
        $cashierId = (string) Str::uuid();

        DB::table('users')->insert([
            [
                'id' => $adminId,
                'name' => 'Admin User',
                'email' => 'admin@classicpos.app',
                'password' => Hash::make('@NOTcomplicated2024P@ssw0rd'),
                'branch_id' => $nairobiBranchId,
                'is_active' => true,
                'is_protected' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'id' => $cashierId,
                'name' => 'Cashier User',
                'email' => 'cashier@classicpos.app',
                'password' => Hash::make('password'),
                'branch_id' => $nairobiBranchId,
                'is_active' => true,
                'is_protected' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('role_user')->insert([
            [
                'user_id' => $adminId,
                'role_id' => $adminRoleId,
                'branch_id' => $nairobiBranchId,
            ],
            [
                'user_id' => $cashierId,
                'role_id' => $cashierRoleId,
                'branch_id' => $nairobiBranchId,
            ],
        ]);

        DB::table('branch_user')->insert([
            ['user_id' => $adminId, 'branch_id' => $nairobiBranchId, 'created_at' => now(), 'updated_at' => now()],
            ['user_id' => $cashierId, 'branch_id' => $nairobiBranchId, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}
