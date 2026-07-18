<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class TicketControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->seed(RolePermissionSeeder::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;

        $adminRole = \App\Models\Role::where('name', 'admin')->first();
        $branch = Branch::factory()->create();
        $this->user->roles()->attach($adminRole->id, ['branch_id' => $branch->id]);
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_list_tickets(): void
    {
        $response = $this->getJson('/api/v1/tickets', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure(['data']);
    }

    public function test_can_create_ticket(): void
    {
        $response = $this->postJson('/api/v1/tickets', [
            'subject' => 'Login issue',
            'description' => 'I cannot log in to my account',
            'category' => 'technical',
            'priority' => 'high',
        ], $this->headers());

        $response->assertStatus(201);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/tickets', [], $this->headers());

        $response->assertStatus(422);
    }

    public function test_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/tickets');

        $response->assertStatus(401);
    }
}
