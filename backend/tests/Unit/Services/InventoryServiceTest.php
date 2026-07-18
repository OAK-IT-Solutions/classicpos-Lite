<?php

namespace Tests\Unit\Services;

use App\Models\Branch;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryServiceTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryService $service;
    protected Branch $branch;
    protected Warehouse $warehouse;
    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(InventoryService::class);

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
            'quantity' => 20,
            'reserved_quantity' => 0,
        ]);
    }

    public function test_check_stock_returns_true_when_sufficient(): void
    {
        $result = $this->service->checkStock($this->product->id, $this->branch->id, 5);

        $this->assertTrue($result);
    }

    public function test_check_stock_returns_false_when_insufficient(): void
    {
        $result = $this->service->checkStock($this->product->id, $this->branch->id, 999);

        $this->assertFalse($result);
    }

    public function test_check_stock_considers_reserved_quantity(): void
    {
        Inventory::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->update(['reserved_quantity' => 18]);

        $result = $this->service->checkStock($this->product->id, $this->branch->id, 5);

        $this->assertFalse($result);
    }

    public function test_reserve_stock_increases_reserved_quantity(): void
    {
        $this->service->reserveStock($this->product->id, $this->branch->id, 5);

        $inventory = Inventory::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(5, $inventory->reserved_quantity);
    }

    public function test_reserve_stock_throws_on_insufficient(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->reserveStock($this->product->id, $this->branch->id, 999);
    }

    public function test_fulfill_reservation_decreases_both_quantities(): void
    {
        Inventory::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->update(['reserved_quantity' => 5]);

        $this->service->fulfillReservation($this->product->id, $this->branch->id, 5);

        $inventory = Inventory::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(15, $inventory->quantity);
        $this->assertEquals(0, $inventory->reserved_quantity);
    }

    public function test_release_reservation_decreases_reserved_only(): void
    {
        Inventory::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->update(['reserved_quantity' => 5]);

        $this->service->releaseReservation($this->product->id, $this->branch->id, 5);

        $inventory = Inventory::where('product_id', $this->product->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->first();

        $this->assertEquals(20, $inventory->quantity);
        $this->assertEquals(0, $inventory->reserved_quantity);
    }
}
