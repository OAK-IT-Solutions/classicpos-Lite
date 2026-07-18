<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class CategoryControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolePermissionSeeder::class);
    }

    protected function authHeaders(): array
    {
        $user = User::factory()->create();
        $branch = Branch::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $user->roles()->attach($adminRole->id, ['branch_id' => $branch->id]);

        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_list_categories(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/categories', $this->authHeaders());

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_can_create_category(): void
    {
        $response = $this->postJson('/api/v1/categories', [
            'name' => 'Electronics',
            'returnable' => true,
        ], $this->authHeaders());

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', ['name' => 'Electronics', 'returnable' => true]);
    }

    public function test_can_update_category(): void
    {
        $category = Category::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson('/api/v1/categories/' . $category->id, [
            'name' => 'New Name',
        ], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseHas('categories', ['id' => $category->id, 'name' => 'New Name']);
    }

    public function test_can_delete_category_without_products(): void
    {
        $category = Category::factory()->create();

        $response = $this->deleteJson('/api/v1/categories/' . $category->id, [], $this->authHeaders());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/categories', [], $this->authHeaders());

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/categories');

        $response->assertStatus(401);
    }
}
