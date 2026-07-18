<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugTest7 extends TestCase
{
    use RefreshDatabase;

    public function test_compare_seed_methods()
    {
        print "\n[DEBUG7] Testing \$this->seed()...\n";

        // First, run via $this->seed()
        $this->artisan('db:seed', [
            '--class' => RolePermissionSeeder::class,
            '--no-interaction' => true,
        ]);

        $countAfterArtisan = DB::table('roles')->count();
        print "[DEBUG7] Roles after \$this->seed(): $countAfterArtisan\n";

        if ($countAfterArtisan > 0) {
            foreach (DB::table('roles')->get() as $r) {
                print "  - {$r->name}\n";
            }
        }

        // Clean up
        DB::table('roles')->delete();
        DB::table('permissions')->delete();
        DB::table('permission_role')->delete();

        print "[DEBUG7] Testing direct seeder call...\n";

        // Now run directly
        $seeder = new RolePermissionSeeder();
        $seeder->setContainer(app());
        $seeder->run();

        $countAfterDirect = DB::table('roles')->count();
        print "[DEBUG7] Roles after direct call: $countAfterDirect\n";

        $this->assertGreaterThan(0, $countAfterArtisan, 'Artisan seed should insert roles');
    }
}
