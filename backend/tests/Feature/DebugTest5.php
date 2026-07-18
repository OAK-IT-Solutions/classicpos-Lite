<?php

namespace Tests\Feature;

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugTest5 extends TestCase
{
    use RefreshDatabase;

    public function test_check_roles_before_seed()
    {
        // Check roles before any insert
        $beforeRoles = DB::table('roles')->count();
        print "\n[DEBUG5] Roles BEFORE seed: $beforeRoles\n";
        if ($beforeRoles > 0) {
            foreach (DB::table('roles')->get() as $r) {
                print "  - {$r->name} (guard: {$r->guard_name})\n";
            }
        }

        $this->seed(RolePermissionSeeder::class);

        $afterRoles = DB::table('roles')->count();
        print "[DEBUG5] Roles AFTER seed: $afterRoles\n";
        if ($afterRoles > 0) {
            foreach (DB::table('roles')->get() as $r) {
                print "  - {$r->name} (guard: {$r->guard_name})\n";
            }
        }

        // Check connections
        $pdo1 = DB::connection('pgsql')->getPdo();
        print "[DEBUG5] PDO connection ID: " . spl_object_id($pdo1) . "\n";

        $adminRole = Role::where('name', 'admin')->first();
        print "[DEBUG5] Admin role: " . ($adminRole ? 'id=' . $adminRole->id : 'null') . "\n";

        $this->assertNotNull($adminRole);
    }
}
