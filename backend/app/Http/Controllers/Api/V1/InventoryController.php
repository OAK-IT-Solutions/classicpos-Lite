<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Product;
use App\Models\Warehouse;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/inventory")]
class InventoryController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    #[OA\Put(path: "/inventory", tags: ["Inventory"], summary: "Update inventory stock", responses: [new OA\Response(response: 200, description: "Inventory updated")])]
    public function update(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'updates' => 'required|array',
            'updates.*.product_id' => 'required|exists:products,id',
            'updates.*.quantity' => 'required|numeric',
        ]);

        try {
            $this->inventoryService->updateStock($validated['updates']);

            return response()->json(['message' => 'Inventory updated successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_INVENTORY_UPDATE',
                    'message' => $e->getMessage(),
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }
    }

    #[OA\Get(path: "/inventory/stock", tags: ["Inventory"], summary: "Get stock levels", responses: [new OA\Response(response: 200, description: "Stock data")])]
    public function stock(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'product_id' => 'nullable|exists:products,id',
            'search' => 'nullable|string|max:255',
            'low_stock' => 'nullable|boolean',
            'category_id' => 'nullable|uuid|exists:categories,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
        ]);

        $query = Inventory::with(['product.category']);

        if (!empty($validated['warehouse_id'])) {
            $query->where('warehouse_id', $validated['warehouse_id']);
        } elseif (!empty($validated['branch_id'])) {
            $warehouseIds = Warehouse::where('branch_id', $validated['branch_id'])
                ->where('is_active', true)
                ->pluck('id');

            if ($warehouseIds->isEmpty()) {
                return response()->json(['data' => []]);
            }

            $query->whereIn('warehouse_id', $warehouseIds);
        }

        if (!empty($validated['product_id'])) {
            $query->where('product_id', $validated['product_id']);
        }

        if (!empty($validated['search'])) {
            $query->whereHas('product', function ($q) use ($validated) {
                $q->whereRaw('LOWER(name) LIKE ?', ['%' . strtolower($validated['search']) . '%']);
            });
        }

        if (!empty($validated['category_id'])) {
            $query->whereHas('product', function ($q) use ($validated) {
                $q->where('category_id', $validated['category_id']);
            });
        }

        if (!empty($validated['low_stock'])) {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('inventory.quantity', '<=', 'products.min_stock');
            });
        }

        $items = $query->get()->map(fn (Inventory $inventory) => [
            'id' => $inventory->id,
            'product_id' => $inventory->product_id,
            'product_name' => $inventory->product->name ?? null,
            'warehouse_id' => $inventory->warehouse_id,
            'quantity' => $inventory->quantity,
            'batch_number' => $inventory->batch_number,
            'expiry_date' => $inventory->expiry_date?->toDateString(),
            'serial_number' => $inventory->serial_number,
        ]);

        return response()->json(['data' => $items]);
    }

    #[OA\Get(path: "/inventory/{id}/movements", tags: ["Inventory"], summary: "Get stock movements", responses: [new OA\Response(response: 200, description: "Movement history")])]
    public function movements(string $id): JsonResponse
    {
        $inventory = Inventory::findOrFail($id);

        $movements = StockMovement::where('inventory_id', $id)
            ->orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'data' => $movements->map(fn (StockMovement $m) => [
                'id' => $m->id,
                'quantity_change' => (float) $m->quantity_change,
                'running_balance' => (float) $m->running_balance,
                'reference_type' => $m->reference_type,
                'reference_id' => $m->reference_id,
                'reason' => $m->reason,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
            'current_quantity' => (float) $inventory->quantity,
            'product_id' => $inventory->product_id,
            'warehouse_id' => $inventory->warehouse_id,
        ]);
    }
}
