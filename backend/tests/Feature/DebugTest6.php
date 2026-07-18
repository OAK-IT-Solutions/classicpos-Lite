<?php

namespace Tests\Feature;

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebugTest6 extends TestCase
{
    use RefreshDatabase;

    public function test_seed_inserts_directly()
    {
        print "\n[DEBUG6] Testing direct insert...\n";

        // Test a direct insert
        $id = (string) Str::uuid();
        try {
            $result = DB::table('roles')->insert([
                'id' => $id,
                'name' => 'test_role_direct',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            print "[DEBUG6] Direct insert result: " . ($result ? 'true' : 'false') . "\n";
        } catch (\Exception $e) {
            print "[DEBUG6] Direct insert error: " . $e->getMessage() . "\n";
        }

        $count = DB::table('roles')->count();
        print "[DEBUG6] Roles count after direct insert: $count\n";
        if ($count > 0) {
            foreach (DB::table('roles')->get() as $r) {
                print "  - {$r->name}\n";
            }
        }

        // Clear before running seeder
        DB::table('roles')->where('name', 'test_role_direct')->delete();

        // Now run the actual seeder
        print "[DEBUG6] Running RolePermissionSeeder...\n";
        $seeder = new RolePermissionSeeder();
        $seeder->setContainer(app());
        $seeder->run();

        $afterCount = DB::table('roles')->count();
        print "[DEBUG6] Roles after seeder::run(): $afterCount\n";
        if ($afterCount > 0) {
            foreach (DB::table('roles')->get() as $r) {
                print "  - {$r->name}\n";
            }
        }

        $this->assertGreaterThan(0, $afterCount, 'Should have roles after seeding');
    }
}
