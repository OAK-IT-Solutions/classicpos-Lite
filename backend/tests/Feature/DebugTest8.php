<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DebugTest8 extends TestCase
{
    use RefreshDatabase;

    public function test_artisan_runs_seeder()
    {
        print "\n[DEBUG8] Testing artisan db:seed via PendingCommand...\n";

        // Call artisan with a custom class that should fail
        $this->artisan('db:seed', [
            '--class' => 'Tests\\Feature\\DebugTestSeeder',
            '--no-interaction' => true,
        ]);

        $roles = DB::table('roles')->get();
        print "[DEBUG8] Roles after artisan seed: " . $roles->count() . "\n";

        // Now try to manually create a role
        $id = (string) Str::uuid();
        DB::table('roles')->insert([
            'id' => $id,
            'name' => 'manual_role',
            'guard_name' => 'web',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roles2 = DB::table('roles')->get();
        print "[DEBUG8] Roles after manual insert: " . $roles2->count() . "\n";
        foreach ($roles2 as $r) {
            print "  - {$r->name}\n";
        }

        $this->assertGreaterThan(0, DB::table('roles')->count(), 'Should have at least manual role');
    }
}
