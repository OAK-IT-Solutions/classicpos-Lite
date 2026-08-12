<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebugTest12 extends TestCase
{
    use RefreshDatabase;

    public function test_compare_approaches()
    {
        print "\n[DEBUG12]\n";

        // Approach A: $kernel->call() without --force
        DB::table('roles')->truncate();
        $kernel = $this->app->make(\Illuminate\Contracts\Console\Kernel::class);
        $outputA = new \Symfony\Component\Console\Output\BufferedOutput();
        $exitA = $kernel->call('db:seed', [
            '--class' => 'Database\Seeders\RolePermissionSeeder',
            '--no-interaction' => true,
        ], $outputA);
        $countA = DB::table('roles')->count();
        print "A) kernel->call (no --force): exit=$exitA, roles=$countA\n";
        print "   Output: " . $outputA->fetch() . "\n";

        // Clean up
        DB::table('roles')->truncate();
        DB::table('permissions')->truncate();
        DB::table('permission_role')->truncate();

        // Approach B: $kernel->call() with --force
        $outputB = new \Symfony\Component\Console\Output\BufferedOutput();
        $exitB = $kernel->call('db:seed', [
            '--class' => 'Database\Seeders\RolePermissionSeeder',
            '--no-interaction' => true,
            '--force' => true,
        ], $outputB);
        $countB = DB::table('roles')->count();
        print "B) kernel->call (with --force): exit=$exitB, roles=$countB\n";
        print "   Output: " . $outputB->fetch() . "\n";

        // Clean up
        DB::table('roles')->truncate();
        DB::table('permissions')->truncate();
        DB::table('permission_role')->truncate();

        // Approach C: using withoutMockingConsoleOutput + artisan
        $this->withoutMockingConsoleOutput();
        $exitC = $this->artisan('db:seed', [
            '--class' => 'Database\Seeders\RolePermissionSeeder',
            '--no-interaction' => true,
        ]);
        $countC = DB::table('roles')->count();
        print "C) artisan (no mock, no --force): exit=$exitC, roles=$countC\n";

        // Clean up
        DB::table('roles')->truncate();
        DB::table('permissions')->truncate();
        DB::table('permission_role')->truncate();

        // Approach D: using withoutMockingConsoleOutput + artisan + --force
        $this->withoutMockingConsoleOutput();
        $exitD = $this->artisan('db:seed', [
            '--class' => 'Database\Seeders\RolePermissionSeeder',
            '--no-interaction' => true,
            '--force' => true,
        ]);
        $countD = DB::table('roles')->count();
        print "D) artisan (no mock, with --force): exit=$exitD, roles=$countD\n";

        $this->assertTrue(true);
    }
}
