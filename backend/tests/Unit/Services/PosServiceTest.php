<?php

namespace Tests\Unit\Services;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Warehouse;
use App\Services\AccountingService;
use App\Services\PosService;
use App\Services\PromotionService;
use App\Services\TaxService;
use App\Services\LoyaltyService;
use App\Services\PaymentService;
use App\Services\InventoryService;
use App\Services\IntegrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PosServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PosService $service;
    protected Branch $branch;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $promotionService = app(PromotionService::class);
        $taxService = app(TaxService::class);
        $loyaltyService = app(LoyaltyService::class);
        $paymentService = app(PaymentService::class);
        $inventoryService = app(InventoryService::class);
        $accountingService = app(AccountingService::class);
        $integrationService = app(IntegrationService::class);

        $this->service = new PosService($promotionService, $taxService, $loyaltyService, $paymentService, $inventoryService, $accountingService, $integrationService);
    }

    private function setupBranchWithStock(int $qty = 10): void
    {
        $this->branch = Branch::factory()->create();
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
            'quantity' => $qty,
        ]);
    }

    public function test_create_sale_successfully(): void
    {
        $this->setupBranchWithStock(10);

        $result = $this->service->createSale([
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 2, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ]);

        $this->assertArrayHasKey('sale_id', $result);
        $this->assertArrayHasKey('invoice_number', $result);
        $this->assertEquals(50.0, $result['subtotal']);
        $this->assertEquals(0, $result['discount']);
        $this->assertEquals(0, $result['tax_amount']);
        $this->assertEquals(50.0, $result['total_amount']);
        $this->assertEquals('completed', $result['status']);
    }

    public function test_create_sale_fails_on_insufficient_stock(): void
    {
        $this->setupBranchWithStock(1);

        try {
            $this->service->createSale([
                'branch_id' => $this->branch->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 5, 'price' => 25.00],
                ],
                'payment_method' => 'cash',
            ]);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Insufficient stock available for reservation.', $e->getMessage());
        }
    }

    public function test_create_sale_fails_without_active_warehouse(): void
    {
        $this->setupBranchWithStock(10);
        $this->warehouse->update(['is_active' => false]);

        try {
            $this->service->createSale([
                'branch_id' => $this->branch->id,
                'items' => [
                    ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 25.00],
                ],
                'payment_method' => 'cash',
            ]);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $e) {
            $this->assertEquals('No active warehouse found for this branch.', $e->getMessage());
        }
    }

    public function test_create_sale_decrements_inventory(): void
    {
        $this->setupBranchWithStock(10);

        $this->service->createSale([
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 3, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ]);

        $inventory = Inventory::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(7, $inventory->quantity);
    }

    public function test_get_sales_list_filters_by_branch(): void
    {
        $this->setupBranchWithStock(10);

        $this->service->createSale([
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ]);

        $result = $this->service->getSalesList(['branch_id' => $this->branch->id]);

        $this->assertEquals(1, $result['total']);
        $this->assertCount(1, $result['data']);
    }

    public function test_get_sales_list_returns_empty_for_unknown_branch(): void
    {
        $this->setupBranchWithStock(10);

        $this->service->createSale([
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ]);

        $result = $this->service->getSalesList(['branch_id' => (string) \Illuminate\Support\Str::uuid()]);

        $this->assertEquals(0, $result['total']);
    }

    public function test_get_sale_detail_returns_sale(): void
    {
        $this->setupBranchWithStock(10);

        $created = $this->service->createSale([
            'branch_id' => $this->branch->id,
            'items' => [
                ['product_id' => $this->product->id, 'quantity' => 1, 'price' => 25.00],
            ],
            'payment_method' => 'cash',
        ]);

        $detail = $this->service->getSaleDetail($created['sale_id']);

        $this->assertEquals($created['sale_id'], $detail->resource->id);
        $this->assertEquals($created['total_amount'], $detail->resource->total_amount);
    }
}
