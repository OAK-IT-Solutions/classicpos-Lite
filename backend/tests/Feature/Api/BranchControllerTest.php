<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class BranchControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;

        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $this->branch = Branch::factory()->create();
        $this->user->roles()->attach($adminRole->id, ['branch_id' => $this->branch->id]);
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_list_branches(): void
    {
        Branch::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/branches', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_show_branch(): void
    {
        $response = $this->getJson('/api/v1/branches/' . $this->branch->id, $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $this->branch->id);
    }

    public function test_can_update_branch(): void
    {
        $response = $this->putJson('/api/v1/branches/' . $this->branch->id, [
            'name' => 'Updated Name',
        ], $this->headers());

        $response->assertStatus(200);
        $this->assertDatabaseHas('branches', ['id' => $this->branch->id, 'name' => 'Updated Name']);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/branches', [], $this->headers());

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/branches');

        $response->assertStatus(401);
    }
}
