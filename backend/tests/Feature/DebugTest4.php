<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugTest4 extends TestCase
{
    use RefreshDatabase;

    public function test_seed_after_saas()
    {
        $this->branch = Branch::factory()->create();
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->seed(RolePermissionSeeder::class);

        $roles = DB::table('roles')->get();
        print "\n[DEBUG4] Roles after seed: " . $roles->count() . "\n";
        foreach ($roles as $r) {
            print "  - {$r->name}\n";
        }

        $adminRole = Role::where('name', 'admin')->first();
        print "[DEBUG4] Admin role found: " . ($adminRole ? 'YES (id=' . $adminRole->id . ')' : 'NO') . "\n";

        $this->assertNotNull($adminRole, 'Admin role should exist after seeding');
    }
}
