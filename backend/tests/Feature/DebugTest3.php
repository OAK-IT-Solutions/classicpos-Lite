<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugTest3 extends TestCase
{
    use RefreshDatabase;

    public function test_has_tables()
    {
        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
        $tableNames = array_map(fn($t) => $t->table_name, $tables);
        sort($tableNames);

        print "\n[DEBUG3] Tables after setUp (" . count($tables) . " total):\n";
        foreach ($tableNames as $tn) {
            print "  - $tn\n";
        }

        $hasBranches = in_array('branches', $tableNames);
        $hasUsers = in_array('users', $tableNames);
        print "[DEBUG3] Has 'branches': " . ($hasBranches ? 'YES' : 'NO') . "\n";
        print "[DEBUG3] Has 'users': " . ($hasUsers ? 'YES' : 'NO') . "\n";

        $this->assertTrue($hasBranches, "branches table should exist");
    }
}
