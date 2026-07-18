<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DebugTest15 extends TestCase
{
    use RefreshDatabase;

    public function test_check_role_user_columns()
    {
        $columns = DB::select("
            SELECT column_name, data_type 
            FROM information_schema.columns 
            WHERE table_name = 'role_user' 
            ORDER BY ordinal_position
        ");
        print "\n[DEBUG15] role_user columns:\n";
        foreach ($columns as $col) {
            print "  - {$col->column_name} ({$col->data_type})\n";
        }

        $hasBranchId = DB::select("
            SELECT column_name FROM information_schema.columns 
            WHERE table_name = 'role_user' AND column_name = 'branch_id'
        ");
        print "[DEBUG15] Has branch_id: " . (count($hasBranchId) > 0 ? 'YES' : 'NO') . "\n";

        $this->assertTrue(true);
    }
}
