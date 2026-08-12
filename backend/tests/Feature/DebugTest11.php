<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebugTest11 extends TestCase
{
    use RefreshDatabase;

    public function test_capture_artisan_output()
    {
        print "\n[DEBUG11] Testing artisan with output capture...\n";

        $manualId = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $manualId,
            'name' => 'pre_artisan_role',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Use kernel call with a BufferedOutput to capture output
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();

        try {
            $exitCode = $kernel->call('db:seed', [
                '--class' => 'Database\Seeders\RolePermissionSeeder',
                '--no-interaction' => true,
                '--force' => true,
            ], $output);
            print "[DEBUG11] Exit code: $exitCode\n";
            print "[DEBUG11] Output:\n" . $output->fetch() . "\n";
        } catch (\Exception $e) {
            print "[DEBUG11] Exception: " . get_class($e) . ": " . $e->getMessage() . "\n";
        }

        $count = DB::table('roles')->count();
        print "[DEBUG11] Roles after artisan: $count\n";

        $this->assertTrue(true);
    }
}
