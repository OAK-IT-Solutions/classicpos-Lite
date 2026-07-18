<?php

namespace Tests\Feature;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DebugTestSeeder extends Seeder
{
    public function run(): void
    {
        $id = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $id,
            'name' => 'debug_test_role',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
