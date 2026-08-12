<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugTest16 extends TestCase
{
    use RefreshDatabase;

    public function test_check_migration_status()
    {
        $migrations = DB::table('migrations')->orderBy('id')->get();
        print "\n[DEBUG16] Migration batches:\n";
        foreach ($migrations as $m) {
            print "  batch={$m->batch} migration={$m->migration}\n";
        }

        // Check if the specific migration was run
        $found = $migrations->first(fn($m) => str_contains($m->migration, 'add_branch_id_to_role_user'));
        print "[DEBUG16] add_branch_id migration found: " . ($found ? "YES (batch={$found->batch})" : "NO") . "\n";

        // Check role_user columns
        $columns = DB::select("
            SELECT column_name FROM information_schema.columns 
            WHERE table_name = 'role_user' 
            ORDER BY ordinal_position
        ");
        print "[DEBUG16] role_user columns: " . implode(', ', array_map(fn($c) => $c->column_name, $columns)) . "\n";

        $this->assertTrue(true);
    }
}
