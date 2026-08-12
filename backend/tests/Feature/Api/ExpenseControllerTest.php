<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Expense;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExpenseControllerTest extends TestCase
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

    public function test_can_list_expenses(): void
    {
        Expense::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $this->branch->id,
            'payee' => 'Vendor One',
            'amount' => 1000.00,
            'method' => 'cash',
            'category' => 'Rent',
            'expense_date' => now()->format('Y-m-d'),
            'notes' => 'First expense',
        ]);

        Expense::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $this->branch->id,
            'payee' => 'Vendor Two',
            'amount' => 2500.00,
            'method' => 'bank_transfer',
            'category' => 'Utilities',
            'expense_date' => now()->format('Y-m-d'),
            'notes' => 'Second expense',
        ]);

        Expense::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $this->branch->id,
            'payee' => 'Vendor Three',
            'amount' => 3000.00,
            'method' => 'mobile_money',
            'category' => 'Transport',
            'expense_date' => now()->format('Y-m-d'),
            'notes' => 'Third expense',
        ]);

        $response = $this->getJson('/api/v1/expenses', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_expense(): void
    {
        $response = $this->postJson('/api/v1/expenses', [
            'payee' => 'Test Vendor',
            'amount' => 5000.00,
            'method' => 'cash',
            'category' => 'Rent',
            'expense_date' => now()->format('Y-m-d'),
            'notes' => 'Test expense',
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertDatabaseHas('expenses', ['payee' => 'Test Vendor', 'amount' => 5000.00]);
    }

    public function test_can_show_expense(): void
    {
        $expense = Expense::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $this->branch->id,
            'payee' => 'Test Vendor',
            'amount' => 5000.00,
            'method' => 'cash',
            'category' => 'Rent',
            'expense_date' => now()->format('Y-m-d'),
            'notes' => 'Test expense',
        ]);

        $response = $this->getJson('/api/v1/expenses/' . $expense->id, $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $expense->id);
    }

    public function test_can_update_expense(): void
    {
        $expense = Expense::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $this->branch->id,
            'payee' => 'Test Vendor',
            'amount' => 5000.00,
            'method' => 'cash',
            'category' => 'Rent',
            'expense_date' => now()->format('Y-m-d'),
            'notes' => 'Test expense',
        ]);

        $response = $this->putJson('/api/v1/expenses/' . $expense->id, [
            'payee' => 'Updated Vendor',
        ], $this->headers());

        $response->assertStatus(200);
        $this->assertDatabaseHas('expenses', ['id' => $expense->id, 'payee' => 'Updated Vendor']);
    }

    public function test_can_delete_expense(): void
    {
        $expense = Expense::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $this->branch->id,
            'payee' => 'Test Vendor',
            'amount' => 5000.00,
            'method' => 'cash',
            'category' => 'Rent',
            'expense_date' => now()->format('Y-m-d'),
            'notes' => 'Test expense',
        ]);

        $response = $this->deleteJson('/api/v1/expenses/' . $expense->id, [], $this->headers());

        $response->assertStatus(200);
        $this->assertDatabaseMissing('expenses', ['id' => $expense->id]);
    }

    public function test_can_get_expense_summary(): void
    {
        Expense::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $this->branch->id,
            'payee' => 'Vendor One',
            'amount' => 1000.00,
            'method' => 'cash',
            'category' => 'Rent',
            'expense_date' => now()->format('Y-m-d'),
        ]);

        Expense::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $this->branch->id,
            'payee' => 'Vendor Two',
            'amount' => 2500.00,
            'method' => 'bank_transfer',
            'category' => 'Utilities',
            'expense_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->getJson('/api/v1/expenses/summary', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'total',
                'month_total',
                'by_category',
            ],
        ]);
    }

    public function test_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/expenses', [], $this->headers());

        $response->assertStatus(422);
        $response->assertJson([
            'error' => ['code' => 'ERR_VALIDATION'],
        ]);
    }

    public function test_validates_invalid_method(): void
    {
        $response = $this->postJson('/api/v1/expenses', [
            'payee' => 'Test Vendor',
            'amount' => 5000.00,
            'method' => 'invalid',
            'category' => 'Rent',
            'expense_date' => now()->format('Y-m-d'),
        ], $this->headers());

        $response->assertStatus(422);
    }
}
