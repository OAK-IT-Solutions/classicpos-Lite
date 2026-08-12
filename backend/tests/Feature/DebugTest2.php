<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugTest2 extends TestCase
{
    use RefreshDatabase;

    public function test_debug()
    {
        $this->assertTrue(true);
    }

    protected function setUp(): void
    {
        parent::setUp();

        print "\n[DEBUG2] RefreshDatabaseState::\$migrated BEFORE setUp: false (but after setUp it's now: " . (RefreshDatabaseState::$migrated ? 'true' : 'false') . ")\n";
        print "[DEBUG2] Default connection: " . config('database.default') . "\n";
        print "[DEBUG2] DB database: " . config('database.connections.pgsql.database') . "\n";

        try {
            $migrator = $this->app->make('migrator');
            $paths = $migrator->paths();
            print "[DEBUG2] Migration paths:\n";
            foreach ($paths as $p) {
                print "  - $p\n";
            }
        } catch (\Exception $e) {
            print "[DEBUG2] Could not get migrator: " . $e->getMessage() . "\n";
        }

        $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'");
        print "[DEBUG2] Tables in public schema: " . count($tables) . "\n";
        $tenantTables = ['branches', 'users', 'products', 'categories', 'inventory', 'sales', 'payments'];
        $foundTenant = false;
        foreach ($tables as $t) {
            print "  - " . $t->table_name . "\n";
            if (in_array($t->table_name, $tenantTables)) {
                $foundTenant = true;
            }
        }
        print "[DEBUG2] Found any tenant table: " . ($foundTenant ? 'YES' : 'NO') . "\n";
    }
}
