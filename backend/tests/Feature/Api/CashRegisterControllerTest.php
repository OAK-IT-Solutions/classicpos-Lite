<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\CashRegisterShift;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class CashRegisterControllerTest extends TestCase
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
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_can_check_status_with_no_open_shift(): void
    {
        $response = $this->getJson('/api/v1/cash-register/status', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data', null);
    }

    public function test_can_open_shift(): void
    {
        $response = $this->postJson('/api/v1/cash-register/open', [
            'password' => 'password',
            'opening_balance' => 100000,
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertDatabaseHas('cash_register_shifts', [
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'status' => 'open',
        ]);
    }

    public function test_cannot_open_shift_without_password(): void
    {
        $response = $this->postJson('/api/v1/cash-register/open', [
            'opening_balance' => 100000,
        ], $this->headers());

        $response->assertStatus(403);
    }

    public function test_cannot_open_shift_with_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/cash-register/open', [
            'password' => 'wrong-password',
            'opening_balance' => 100000,
        ], $this->headers());

        $response->assertStatus(403);
    }

    public function test_cannot_open_two_shifts(): void
    {
        $this->postJson('/api/v1/cash-register/open', [
            'password' => 'password',
            'opening_balance' => 100000,
        ], $this->headers())->assertStatus(201);

        $response = $this->postJson('/api/v1/cash-register/open', [
            'password' => 'password',
            'opening_balance' => 50000,
        ], $this->headers());

        $response->assertStatus(400);
        $response->assertJson([
            'error' => ['code' => 'ERR_SHIFT_OPEN'],
        ]);
    }

    public function test_can_close_shift(): void
    {
        $openResponse = $this->postJson('/api/v1/cash-register/open', [
            'password' => 'password',
            'opening_balance' => 100000,
        ], $this->headers());

        $shiftId = $openResponse->json('data.id');

        $response = $this->postJson("/api/v1/cash-register/{$shiftId}/close", [
            'password' => 'password',
            'actual_balance' => 150000,
        ], $this->headers());

        $response->assertStatus(200);
        $this->assertDatabaseHas('cash_register_shifts', [
            'id' => $shiftId,
            'status' => 'closed',
        ]);
    }

    public function test_cannot_close_shift_without_password(): void
    {
        $openResponse = $this->postJson('/api/v1/cash-register/open', [
            'password' => 'password',
            'opening_balance' => 100000,
        ], $this->headers());

        $shiftId = $openResponse->json('data.id');

        $response = $this->postJson("/api/v1/cash-register/{$shiftId}/close", [
            'actual_balance' => 150000,
        ], $this->headers());

        $response->assertStatus(403);
    }

    public function test_can_list_shifts(): void
    {
        CashRegisterShift::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'opened_at' => now(),
            'opening_balance' => 100000,
            'cash_sales' => 0,
            'expected_balance' => 100000,
            'status' => 'open',
        ]);
        CashRegisterShift::create([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'branch_id' => $this->branch->id,
            'user_id' => $this->user->id,
            'opened_at' => now()->subHours(2),
            'opening_balance' => 50000,
            'cash_sales' => 0,
            'expected_balance' => 50000,
            'status' => 'closed',
            'closed_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/v1/cash-register/shifts', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'branch_id', 'user_id', 'status'],
            ],
            'current_page',
            'total',
        ]);
    }
}
