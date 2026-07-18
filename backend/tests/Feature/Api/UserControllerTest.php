<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->branch = Branch::factory()->create();
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->seed(RolePermissionSeeder::class);
        $adminRole = Role::where('name', 'admin')->first();
        $this->user->roles()->attach($adminRole->id, ['branch_id' => $this->branch->id]);
        $this->user->branches()->attach($this->branch->id);
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_list_users(): void
    {
        User::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/users', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_create_user(): void
    {
        $branch2 = Branch::factory()->create();
        Subscription::create([
            'branch_id' => $branch2->id,
            'plan_type' => 'standard',
            'billing_cycle' => 'monthly',
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
        ]);
        $role = Role::where('name', 'cashier')->first();

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Test User',
            'email' => 'testuser@example.com',
            'password' => 'Password123!',
            'branch_ids' => [$branch2->id],
            'role_id' => $role->id,
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'testuser@example.com']);
    }

    public function test_can_show_user(): void
    {
        $user2 = User::factory()->create();
        $user2->roles()->attach(Role::where('name', 'cashier')->first()->id, ['branch_id' => $this->branch->id]);
        $user2->branches()->attach($this->branch->id);

        $response = $this->getJson('/api/v1/users/' . $user2->id, $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $user2->id);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'roles',
                'branches',
            ],
        ]);
    }

    public function test_can_update_user(): void
    {
        $user2 = User::factory()->create();

        $response = $this->putJson('/api/v1/users/' . $user2->id, [
            'name' => 'Updated Name',
        ], $this->headers());

        $response->assertStatus(200);
        $this->assertDatabaseHas('users', ['id' => $user2->id, 'name' => 'Updated Name']);
    }

    public function test_can_delete_user(): void
    {
        $user2 = User::factory()->create();

        $response = $this->deleteJson('/api/v1/users/' . $user2->id, [], $this->headers());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('users', ['id' => $user2->id]);
    }

    public function test_cannot_delete_self(): void
    {
        $response = $this->deleteJson('/api/v1/users/' . $this->user->id, [], $this->headers());

        $response->assertStatus(400);
        $decoded = json_decode($response->json('message'), true);
        $this->assertEquals('ERR_CANNOT_DELETE_SELF', $decoded['error']['code']);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/users', [], $this->headers());

        $response->assertStatus(422);
        $response->assertJson([
            'error' => ['code' => 'ERR_VALIDATION'],
        ]);
    }

    public function test_can_list_user_roles(): void
    {
        $user2 = User::factory()->create();
        $role = Role::where('name', 'cashier')->first();
        $user2->roles()->attach($role->id, ['branch_id' => $this->branch->id]);

        $response = $this->getJson('/api/v1/users/roles/' . $user2->id, $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }
}
