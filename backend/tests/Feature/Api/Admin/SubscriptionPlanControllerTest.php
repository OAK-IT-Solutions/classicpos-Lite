<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Branch;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\SaaS;

class SubscriptionPlanControllerTest extends SaaS
{

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['landlord.self_hosted' => true]);

        $branch = Branch::create(['name' => 'HQ', 'location' => 'Nairobi', 'timezone' => 'Africa/Nairobi']);
        $this->admin = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        $this->admin->roles()->attach($role->id, ['branch_id' => $branch->id]);
    }

    private function authHeader(): array
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    private function assertLandlordDatabaseHas(string $table, array $data): void
    {
        $this->assertTrue(
            DB::connection('landlord')->table($table)->where($data)->exists(),
            "Failed asserting that {$table} has matching data on landlord connection"
        );
    }

    private function assertLandlordDatabaseMissing(string $table, array $data): void
    {
        $this->assertFalse(
            DB::connection('landlord')->table($table)->where($data)->exists(),
            "Failed asserting that {$table} does not have matching data on landlord connection"
        );
    }

    public function test_can_list_plans(): void
    {
        SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_branches' => 2,
            'max_users_per_branch' => 5,
            'max_devices_per_branch' => 3,
            'features' => ['pos', 'inventory'],
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/admin/plans', $this->authHeader());
        $response->assertOk();
        $response->assertJsonFragment(['name' => 'Starter']);
    }

    public function test_can_create_plan(): void
    {
        $response = $this->postJson('/api/v1/admin/plans', [
            'name' => 'Pro',
            'slug' => 'pro',
            'price_monthly' => 79,
            'price_yearly' => 790,
            'max_branches' => 10,
            'max_users_per_branch' => 25,
            'max_devices_per_branch' => 15,
            'features' => ['pos', 'inventory', 'reports'],
            'is_active' => true,
        ], $this->authHeader());

        $response->assertCreated();
        $this->assertLandlordDatabaseHas('subscription_plans', ['slug' => 'pro']);
    }

    public function test_can_update_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'price_monthly' => 29,
            'price_yearly' => 290,
            'max_branches' => 2,
            'max_users_per_branch' => 5,
            'max_devices_per_branch' => 3,
            'features' => ['pos'],
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/admin/plans/{$plan->id}", [
            'price_monthly' => 39,
        ], $this->authHeader());

        $response->assertOk();
        $this->assertLandlordDatabaseHas('subscription_plans', ['id' => $plan->id, 'price_monthly' => 39]);
    }

    public function test_can_delete_plan(): void
    {
        $plan = SubscriptionPlan::create([
            'name' => 'ToDelete',
            'slug' => 'to-delete',
            'price_monthly' => 10,
            'price_yearly' => 100,
            'max_branches' => 1,
            'max_users_per_branch' => 1,
            'max_devices_per_branch' => 1,
            'features' => [],
            'is_active' => false,
        ]);

        $response = $this->deleteJson("/api/v1/admin/plans/{$plan->id}", [], $this->authHeader());
        $response->assertOk();
        $this->assertLandlordDatabaseMissing('subscription_plans', ['id' => $plan->id]);
    }
}
