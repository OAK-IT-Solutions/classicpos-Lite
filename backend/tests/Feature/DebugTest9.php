<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebugTest9 extends TestCase
{
    use RefreshDatabase;

    public function test_debug_seed_path()
    {
        print "\n[DEBUG9] Test starts...\n";

        // Let's manually run what seed() does, but with tracing
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);

        // Before artisan, insert a test row manually to confirm DB works
        $manualId = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $manualId,
            'name' => 'pre_artisan_role',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $countAfterManual = DB::table('roles')->count();
        print "[DEBUG9] Roles after manual insert BEFORE artisan: $countAfterManual\n";

        // Now call artisan directly
        $exitCode = $kernel->call('db:seed', [
            '--class' => 'Database\Seeders\RolePermissionSeeder',
            '--no-interaction' => true,
        ]);
        print "[DEBUG9] Artisan exit code: $exitCode\n";

        $countAfterArtisan = DB::table('roles')->count();
        print "[DEBUG9] Roles after artisan call: $countAfterArtisan\n";
        if ($countAfterArtisan > 0) {
            foreach (DB::table('roles')->get() as $r) {
                print "  - {$r->name}\n";
            }
        }

        $this->assertGreaterThan(1, $countAfterArtisan, 'Artisan seed should add roles');
    }
}
