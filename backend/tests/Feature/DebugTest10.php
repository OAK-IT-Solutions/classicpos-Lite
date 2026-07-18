<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebugTest10 extends TestCase
{
    use RefreshDatabase;

    public function test_capture_artisan_exception()
    {
        print "\n[DEBUG10] Testing artisan and capturing output...\n";

        // Insert a manual role
        $manualId = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $manualId,
            'name' => 'pre_artisan_role',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run artisan without mocking - get the real output
        $this->withoutMockingConsoleOutput();

        try {
            $exitCode = $this->artisan('db:seed', [
                '--class' => 'Database\Seeders\RolePermissionSeeder',
                '--no-interaction' => true,
            ]);
            print "[DEBUG10] Exit code: $exitCode\n";
        } catch (\Exception $e) {
            print "[DEBUG10] Exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
            print "[DEBUG10] Trace:\n" . $e->getTraceAsString() . "\n";
        }

        $count = DB::table('roles')->count();
        print "[DEBUG10] Roles after artisan: $count\n";

        $this->assertTrue(true); // just to get output
    }
}
