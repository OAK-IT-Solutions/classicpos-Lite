<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class ProductsTest extends TestCase
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

    public function test_can_list_products(): void
    {
        Product::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/products', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'current_page', 'total']);
        $this->assertCount(3, $response->json('data'));
    }

    public function test_can_create_product(): void
    {
        $category = Category::factory()->create();

        $response = $this->postJson('/api/v1/products', [
            'name' => 'Test Product',
            'barcode' => 'TEST123',
            'price' => 19.99,
            'cost' => 10.00,
            'category_id' => $category->id,
            'stock_uom' => 'pcs',
            'min_stock' => 5,
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertDatabaseHas('products', ['name' => 'Test Product', 'barcode' => 'TEST123']);
    }

    public function test_can_show_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->getJson('/api/v1/products/' . $product->id, $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $product->id);
    }

    public function test_can_update_product(): void
    {
        $product = Product::factory()->create(['price' => 10.00]);

        $response = $this->putJson('/api/v1/products/' . $product->id, [
            'price' => 25.00,
        ], $this->headers());

        $response->assertStatus(200);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'price' => 25.00]);
    }

    public function test_can_delete_product(): void
    {
        $product = Product::factory()->create();

        $response = $this->deleteJson('/api/v1/products/' . $product->id, [], $this->headers());

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Product deactivated successfully.']);
        $this->assertDatabaseHas('products', ['id' => $product->id, 'is_active' => false]);
    }

    public function test_can_fetch_by_barcode(): void
    {
        Product::factory()->create(['barcode' => 'BARCODE123']);

        $response = $this->getJson('/api/v1/products/by-barcode/BARCODE123', $this->headers());

        $response->assertStatus(200);
        $response->assertJsonPath('data.barcode', 'BARCODE123');
    }

    public function test_returns_404_for_unknown_barcode(): void
    {
        $response = $this->getJson('/api/v1/products/by-barcode/NONEXISTENT', $this->headers());

        $response->assertStatus(404);
    }

    public function test_validates_required_fields_on_create(): void
    {
        $response = $this->postJson('/api/v1/products', [], $this->headers());

        $response->assertStatus(422);
        $response->assertJson([
            'error' => ['code' => 'ERR_VALIDATION'],
        ]);
    }
}
