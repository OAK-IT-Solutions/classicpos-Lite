<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/inventory-adjustments")]
class InventoryAdjustmentController extends Controller
{
    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    #[OA\Get(path: "/inventory-adjustments", tags: ["Inventory Adjustments"], summary: "List inventory adjustments", responses: [new OA\Response(response: 200, description: "Paginated adjustments")])]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $branchIds = $user->branches()->pluck('branches.id')->toArray();
        if (empty($branchIds)) {
            $branchIds = [$user->branch_id];
        }

        $query = InventoryAdjustment::with('product')
            ->whereIn('branch_id', $branchIds)
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $adjustments = $query->paginate($request->per_page ?? 20);

        return response()->json($adjustments);
    }

    #[OA\Post(path: "/inventory-adjustments", tags: ["Inventory Adjustments"], summary: "Create an inventory adjustment", responses: [new OA\Response(response: 201, description: "Adjustment created")])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'product_id' => 'required|uuid|exists:products,id',
            'warehouse_id' => 'required|uuid|exists:warehouses,id',
            'quantity' => 'required|numeric|min:0.001',
            'type' => 'required|string|in:damaged,defect,expired,stolen,write_off,correction',
            'reason' => 'required|string|max:500',
            'reference' => 'nullable|string|max:255',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $warehouse = Warehouse::findOrFail($validated['warehouse_id']);

            $inventory = Inventory::where('product_id', $validated['product_id'])
                ->where('warehouse_id', $validated['warehouse_id'])
                ->lockForUpdate()
                ->first();

            if (!$inventory || $inventory->quantity < $validated['quantity']) {
                throw new \RuntimeException('Insufficient stock for this adjustment.');
            }

            $inventory->decrement('quantity', $validated['quantity']);
            $inventory->refresh();

            $adjustment = InventoryAdjustment::create([
                'id' => (string) Str::uuid(),
                'branch_id' => $warehouse->branch_id,
                'product_id' => $validated['product_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'quantity' => $validated['quantity'],
                'type' => $validated['type'],
                'reason' => $validated['reason'],
                'reference' => $validated['reference'] ?? null,
            ]);

            StockMovement::create([
                'id' => (string) Str::uuid(),
                'inventory_id' => $inventory->id,
                'product_id' => $validated['product_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'quantity_change' => -$validated['quantity'],
                'running_balance' => $inventory->quantity,
                'reference_type' => $validated['type'],
                'reason' => $validated['reason'],
            ]);

            $this->createAdjustmentJournalEntry($adjustment);

            return response()->json(['data' => $adjustment], 201);
        });
    }

    private function createAdjustmentJournalEntry(InventoryAdjustment $adjustment): void
    {
        $product = Product::find($adjustment->product_id);
        if (!$product || !$product->cost) {
            return;
        }

        $totalCost = (float) $product->cost * (float) $adjustment->quantity;

        try {
            $this->accountingService->createJournalEntry(
                branchId: $adjustment->branch_id,
                entryDate: now()->format('Y-m-d'),
                description: "Inventory adjustment: {$adjustment->reason}",
                lines: [
                    [
                        'account_code' => '5140',
                        'debit' => $totalCost,
                        'credit' => 0,
                        'description' => $adjustment->type,
                    ],
                    [
                        'account_code' => '1330',
                        'debit' => 0,
                        'credit' => $totalCost,
                        'description' => 'Inventory write-off',
                    ],
                ],
                referenceType: 'adjustment',
                referenceId: $adjustment->id,
            );
        } catch (\Exception $e) {
            Log::error('Failed to create adjustment journal entry', [
                'adjustment_id' => $adjustment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    #[OA\Get(path: "/inventory-adjustments/types", tags: ["Inventory Adjustments"], summary: "List adjustment types", responses: [new OA\Response(response: 200, description: "Adjustment types")])]
    public function types(): JsonResponse
    {
        return response()->json(['data' => [
            ['value' => 'damaged', 'label' => 'Damaged Goods'],
            ['value' => 'defect', 'label' => 'Manufacturing Defect'],
            ['value' => 'expired', 'label' => 'Expired Stock'],
            ['value' => 'stolen', 'label' => 'Theft / Missing'],
            ['value' => 'write_off', 'label' => 'General Write-off'],
            ['value' => 'correction', 'label' => 'Inventory Correction'],
        ]]);
    }
}
