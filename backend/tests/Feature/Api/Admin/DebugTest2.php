<?php

namespace Tests\Feature\Api\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

class DebugTest2 extends TestCase
{
    use RefreshDatabase;

    public function test_config_db_name(): void
    {
        $envDb = getenv("DB_DATABASE");
        $configDb = Config::get("database.connections.pgsql.database");
        file_put_contents("php://stderr", "getenv DB_DATABASE: " . ($envDb ?: "NULL") . PHP_EOL);
        file_put_contents("php://stderr", "config DB_DATABASE: " . ($configDb ?: "NULL") . PHP_EOL);
        $this->assertTrue(true);
    }
}
