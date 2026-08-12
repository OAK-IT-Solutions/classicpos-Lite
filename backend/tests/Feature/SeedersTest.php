<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_runs_successfully(): void
    {
        $this->seed();

        $this->assertDatabaseHas('roles', ['name' => 'admin']);
        $this->assertDatabaseHas('roles', ['name' => 'branch_manager']);
        $this->assertDatabaseHas('roles', ['name' => 'cashier']);
        $this->assertDatabaseHas('roles', ['name' => 'inventory_clerk']);
        $this->assertDatabaseHas('categories', ['name' => 'Beverages']);
        $this->assertDatabaseHas('categories', ['name' => 'Snacks']);
    }
}
