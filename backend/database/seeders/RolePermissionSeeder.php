<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'manage_sales',
            'manage_inventory',
            'manage_customers',
            'manage_products',
            'manage_branches',
            'view_reports',
            'process_payments',
            'manage_users',
            'manage_devices',
            'void_sale',
            'approve_return',
            'manage_subscription',
            'manage_business_profile',
            'export_data',
            'manage_accounting',
            'manage_integrations',
        ];

        $existingPermissions = DB::table('permissions')->pluck('id', 'name');
        $permissionIds = [];

        foreach ($permissions as $name) {
            if (isset($existingPermissions[$name])) {
                $permissionIds[$name] = $existingPermissions[$name];
            } else {
                $id = (string) Str::uuid();
                DB::table('permissions')->insert([
                    'id' => $id,
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $permissionIds[$name] = $id;
            }
        }

        $roles = [
            'admin',
            'branch_manager',
            'cashier',
            'inventory_clerk',
        ];

        $roleIds = [];
        foreach ($roles as $name) {
            $existing = DB::table('roles')->where('name', $name)->where('guard_name', 'web')->first();
            if ($existing) {
                $roleIds[$name] = $existing->id;
            } else {
                $id = (string) Str::uuid();
                DB::table('roles')->insert([
                    'id' => $id,
                    'name' => $name,
                    'guard_name' => 'web',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $roleIds[$name] = $id;
            }
        }

        $rolePermissions = [
            'admin' => $permissions,
            'branch_manager' => [
                'manage_sales',
                'manage_inventory',
                'manage_customers',
                'manage_products',
                'view_reports',
                'process_payments',
                'manage_users',
                'void_sale',
                'approve_return',
                'manage_business_profile',
                'export_data',
                'manage_accounting',
                'manage_integrations',
            ],
            'cashier' => [
                'manage_sales',
                'process_payments',
                'manage_customers',
            ],
            'inventory_clerk' => [
                'manage_inventory',
                'manage_products',
            ],
        ];

        foreach ($rolePermissions as $roleName => $perms) {
            foreach ($perms as $permName) {
                $exists = DB::table('permission_role')
                    ->where('permission_id', $permissionIds[$permName])
                    ->where('role_id', $roleIds[$roleName])
                    ->exists();

                if (!$exists) {
                    DB::table('permission_role')->insert([
                        'permission_id' => $permissionIds[$permName],
                        'role_id' => $roleIds[$roleName],
                    ]);
                }
            }
        }
    }
}
