<?php

namespace Tests\Feature\Api;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\TestCase;

class PosSaleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Warehouse $warehouse;
    protected Product $product;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ThrottleRequests::class);

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test-token')->plainTextToken;

        $this->branch = Branch::factory()->create();

        $this->seed(RolePermissionSeeder::class);
        $adminRole = Role::where('name', 'admin')->first();
        $this->user->roles()->attach($adminRole->id, ['branch_id' => $this->branch->id]);
        $this->warehouse = Warehouse::factory()->create([
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
        $this->product = Product::factory()->create([
            'price' => 25.00,
            'is_active' => true,
        ]);
        Inventory::factory()->create([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 20,
        ]);
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/json',
        ];
    }

    public function test_pos_products_endpoint(): void
    {
        $response = $this->getJson('/api/v1/pos/products?branch_id=' . $this->branch->id, $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [['id', 'name', 'price', 'stock', 'category']],
            'categories',
        ]);
    }

    public function test_create_sale_successfully(): void
    {
        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ], $this->headers());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'sale_id',
            'invoice_number',
            'subtotal',
            'total_amount',
            'status',
        ]);
        $this->assertEquals(50.0, $response->json('subtotal'));
        $this->assertEquals('completed', $response->json('status'));
    }

    public function test_create_sale_with_promo_and_tax(): void
    {
        $promo = Promotion::factory()->create([
            'code' => 'WELCOME10',
            'type' => 'percentage',
            'value' => 10,
            'min_order_amount' => 0,
            'max_discount_amount' => 50,
            'usage_limit' => null,
            'is_active' => true,
        ]);

        $taxProfile = TaxProfile::factory()->create([
            'name' => 'VAT 16%',
            'rate' => 16,
            'type' => 'exclusive',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
            'promo_code' => 'WELCOME10',
            'tax_profile_id' => $taxProfile->id,
        ], $this->headers());

        $response->assertStatus(201);
        $this->assertEquals(25.0, $response->json('subtotal'));
        $this->assertEquals(2.5, $response->json('discount'));
        $this->assertEquals(4.0, $response->json('tax_amount'));
        $this->assertEquals(26.5, $response->json('total_amount'));
    }

    public function test_create_sale_rejects_invalid_promo(): void
    {
        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
            'promo_code' => 'INVALID',
        ], $this->headers());

        $response->assertStatus(400);
        $response->assertJson([
            'error' => [
                'code' => 'ERR_SALE_FAILED',
            ],
        ]);
    }

    public function test_create_sale_rejects_insufficient_stock(): void
    {
        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 999, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ], $this->headers());

        $response->assertStatus(400);
        $response->assertJson([
            'error' => [
                'code' => 'ERR_SALE_FAILED',
            ],
        ]);
    }

    public function test_create_sale_requires_auth(): void
    {
        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(401);
    }

    public function test_create_sale_validates_items(): void
    {
        $response = $this->postJson('/api/v1/sales', [
            'branch_id' => $this->branch->id,
            'items' => [],
            'payment_method' => 'cash',
        ], $this->headers());

        $response->assertStatus(422);
        $response->assertJson([
            'error' => [
                'code' => 'ERR_VALIDATION',
            ],
        ]);
        $response->assertJsonStructure([
            'error' => [
                'details' => ['items'],
            ],
        ]);
    }

    public function test_sales_index_endpoint(): void
    {
        $this->postJson('/api/v1/sales', [
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ], $this->headers());

        $response = $this->getJson('/api/v1/sales?branch_id=' . $this->branch->id, $this->headers());

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data',
            'current_page',
            'last_page',
            'per_page',
            'total',
        ]);
        $this->assertEquals(1, $response->json('total'));
    }

    public function test_sales_show_endpoint(): void
    {
        $createResponse = $this->postJson('/api/v1/sales', [
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ], $this->headers());

        $saleId = $createResponse->json('sale_id');

        $response = $this->getJson('/api/v1/sales/' . $saleId, $this->headers());

        $response->assertStatus(200);
        $response->assertJson([
            'id' => $saleId,
        ]);
    }
}
