<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'is_editable' => false],
            ['name' => 'branch_manager', 'is_editable' => false],
            ['name' => 'cashier', 'is_editable' => false],
            ['name' => 'inventory_clerk', 'is_editable' => false],
        ];

        foreach ($roles as $data) {
            $role = Role::firstOrNew(['name' => $data['name'], 'guard_name' => 'web']);

            if (!$role->exists) {
                $role->id = (string) Str::uuid();
                $role->is_editable = $data['is_editable'];
                $role->save();
            } else {
                $role->update(['is_editable' => $data['is_editable']]);
            }
        }
    }
}
