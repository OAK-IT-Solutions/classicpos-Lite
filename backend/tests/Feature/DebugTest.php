<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Tests\TestCase;

class DebugTest extends TestCase
{
    use RefreshDatabase;

    public function test_debug()
    {
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        print "\n[DEBUG] RefreshDatabaseState::\$migrated BEFORE setUp: " . (RefreshDatabaseState::$migrated ? 'true' : 'false') . "\n";
        parent::setUp();
        print "[DEBUG] RefreshDatabaseState::\$migrated AFTER setUp: " . (RefreshDatabaseState::$migrated ? 'true' : 'false') . "\n";
        $tables = \Illuminate\Support\Facades\DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
        print "[DEBUG] Tables in public schema: " . count($tables) . "\n";
        foreach ($tables as $t) {
            print "  - " . $t->table_name . "\n";
        }
    }
}
