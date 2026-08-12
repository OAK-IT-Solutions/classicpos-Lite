<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Branch;
use App\Models\User;
use App\Models\Role;
use App\Models\Landlord\SubscriptionPlan;
use Tests\SaaS;

class DebugTest extends SaaS
{
    protected function setUp(): void
    {
        parent::setUp();
        config(["landlord.self_hosted" => true]);

        $branch = Branch::create(["name" => "HQ", "location" => "Nairobi", "timezone" => "Africa/Nairobi"]);
        $this->admin = User::factory()->create();
        $role = Role::create(["name" => "admin"]);
        $this->admin->roles()->attach($role->id, ["branch_id" => $branch->id]);
    }

    private function authHeader(): array
    {
        $token = $this->admin->createToken("test")->plainTextToken;
        return ["Authorization" => "Bearer $token"];
    }

    public function test_create_plan_debug(): void
    {
        // Check if default DB works before request
        try {
            \Illuminate\Support\Facades\DB::select("SELECT 1");
            file_put_contents("php://stderr", "SELECT 1 BEFORE: OK" . PHP_EOL);
        } catch (\Throwable $e) {
            file_put_contents("php://stderr", "SELECT 1 BEFORE ERROR: " . $e->getMessage() . PHP_EOL);
        }

        // Make the POST request
        $response = $this->postJson("/api/v1/admin/plans", [
            "name" => "Pro",
            "slug" => "pro",
            "price_monthly" => 79,
            "price_yearly" => 790,
            "max_branches" => 10,
            "max_users_per_branch" => 25,
            "max_devices_per_branch" => 15,
            "features" => ["pos", "inventory", "reports"],
            "is_active" => true,
        ], $this->authHeader());

        file_put_contents("php://stderr", "Response status: " . $response->status() . PHP_EOL);

        // Check if default DB works after request
        try {
            \Illuminate\Support\Facades\DB::select("SELECT 1");
            file_put_contents("php://stderr", "SELECT 1 AFTER: OK" . PHP_EOL);
        } catch (\Throwable $e) {
            file_put_contents("php://stderr", "SELECT 1 AFTER ERROR: " . $e->getMessage() . PHP_EOL);
        }

        $this->assertTrue(true);
    }
}
