<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class CustomersTest extends TestCase
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
        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->seed(RolePermissionSeeder::class);
        $adminRole = Role::where('name', 'admin')->first();
        $this->user->roles()->attach($adminRole->id, ['branch_id' => $this->branch->id]);
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_list_customers(): void
    {
        Customer::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/customers', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_customer(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'John Doe',
            'phone' => '+260970000001',
            'email' => 'john@example.com',
            'location' => 'Lusaka',
            'branch_id' => $branch->id,
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertDatabaseHas('customers', ['phone' => '+260970000001']);
    }

    public function test_can_show_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->getJson('/api/v1/customers/' . $customer->id, $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $customer->id);
    }

    public function test_can_update_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->putJson('/api/v1/customers/' . $customer->id, [
            'name' => 'Updated Name',
        ], $this->headers());

        $response->assertStatus(200);
        $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'Updated Name']);
    }

    public function test_can_delete_customer(): void
    {
        $customer = Customer::factory()->create();

        $response = $this->deleteJson('/api/v1/customers/' . $customer->id, [], $this->headers());

        $response->assertStatus(200);
        $this->assertSoftDeleted($customer);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/customers', [], $this->headers());

        $response->assertStatus(422);
        $response->assertJson([
            'error' => ['code' => 'ERR_VALIDATION'],
        ]);
    }

    public function test_validates_unique_phone(): void
    {
        Customer::factory()->create(['phone' => '+260970000001']);

        $response = $this->postJson('/api/v1/customers', [
            'name' => 'Another',
            'phone' => '+260970000001',
        ], $this->headers());

        $response->assertStatus(422);
    }
}
