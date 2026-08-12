<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\ChartOfAccount;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/stock-transfers")]
class StockTransferController extends BaseController
{
    protected string $modelClass = StockTransfer::class;

    protected array $searchableFields = ['notes', 'status'];

    protected array $withRelations = ['fromWarehouse', 'toWarehouse', 'items.product'];

    public function __construct(
        protected AccountingService $accountingService,
    ) {
    }

    protected function rules(Request $request, ?string $id = null): array
    {
        if ($id) {
            return [
                'status' => 'sometimes|required|string|in:pending,in_transit,completed,cancelled',
                'notes' => 'nullable|string|max:1000',
                'transferred_at' => 'nullable|date',
            ];
        }

        return [
            'from_warehouse_id' => 'required|uuid|exists:warehouses,id',
            'to_warehouse_id' => 'required|uuid|exists:warehouses,id|different:from_warehouse_id',
            'notes' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
        ];
    }

    #[OA\Post(path: "/stock-transfers", tags: ["Stock Transfers"], summary: "Create a stock transfer", responses: [new OA\Response(response: 201, description: "Transfer created")])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules($request));
        $items = $validated['items'];
        unset($validated['items']);
        $validated['status'] = 'pending';

        $transfer = DB::transaction(function () use ($validated, $items) {
            $transfer = StockTransfer::create($validated);

            foreach ($items as $item) {
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            return $transfer;
        });

        $transfer->load($this->withRelations);

        return response()->json(['data' => $transfer], 201);
    }

    #[OA\Post(path: "/stock-transfers/{id}/complete", tags: ["Stock Transfers"], summary: "Complete a stock transfer", responses: [new OA\Response(response: 200, description: "Transfer completed")])]
    public function complete(string $id): JsonResponse
    {
        $transfer = StockTransfer::with(['items', 'items.product'])->findOrFail($id);

        if (!in_array($transfer->status, ['pending', 'in_transit'])) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_INVALID_TRANSFER_STATUS',
                    'message' => 'Only pending or in_transit transfers can be completed.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }

        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $fromRecord = Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->from_warehouse_id)
                    ->lockForUpdate()
                    ->first();

                $availableQty = $fromRecord
                    ? $fromRecord->quantity - ($fromRecord->reserved_quantity ?? 0)
                    : 0;

                if ($availableQty < $item->quantity) {
                    throw new \RuntimeException("Insufficient stock for product {$item->product->name}. Available: {$availableQty}, requested: {$item->quantity}");
                }

                $fromRecord->decrement('quantity', $item->quantity);
                $fromRecord->refresh();

                StockMovement::create([
                    'id' => (string) Str::uuid(),
                    'inventory_id' => $fromRecord->id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'quantity_change' => -$item->quantity,
                    'running_balance' => $fromRecord->quantity,
                    'reference_type' => 'transfer_out',
                    'reference_id' => $transfer->id,
                ]);

                $toRecord = Inventory::where('product_id', $item->product_id)
                    ->where('warehouse_id', $transfer->to_warehouse_id)
                    ->lockForUpdate()
                    ->first();

                if ($toRecord) {
                    $toRecord->increment('quantity', $item->quantity);
                    $toRecord->refresh();
                } else {
                    $toRecord = Inventory::create([
                        'id' => (string) Str::uuid(),
                        'product_id' => $item->product_id,
                        'warehouse_id' => $transfer->to_warehouse_id,
                        'quantity' => $item->quantity,
                    ]);
                }

                StockMovement::create([
                    'id' => (string) Str::uuid(),
                    'inventory_id' => $toRecord->id,
                    'product_id' => $item->product_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'quantity_change' => $item->quantity,
                    'running_balance' => $toRecord->quantity,
                    'reference_type' => 'transfer_in',
                    'reference_id' => $transfer->id,
                ]);
            }

            $transfer->update([
                'status' => 'completed',
                'transferred_at' => now(),
            ]);
        });

        $transfer->load($this->withRelations);

        try {
            $this->createTransferJournalEntry($transfer);
        } catch (\Exception $e) {
            Log::error('Failed to create transfer journal entry: ' . $e->getMessage());
        }

        return response()->json(['data' => $transfer]);
    }

    private function createTransferJournalEntry(StockTransfer $transfer): void
    {
        $fromWarehouse = $transfer->fromWarehouse;
        $toWarehouse = $transfer->toWarehouse;

        if ($fromWarehouse->branch_id === $toWarehouse->branch_id) {
            return;
        }

        $fromInventory = ChartOfAccount::where('branch_id', $fromWarehouse->branch_id)
            ->where('code', '1330')->where('is_active', true)->first();
        $toInventory = ChartOfAccount::where('branch_id', $toWarehouse->branch_id)
            ->where('code', '1330')->where('is_active', true)->first();

        if (!$fromInventory || !$toInventory) return;

        $totalCost = $transfer->items->sum(fn ($item) => ($item->product->cost ?? 0) * $item->quantity);
        if ($totalCost <= 0) return;

        $this->accountingService->createJournalEntry(
            branchId: $toWarehouse->branch_id,
            entryDate: now()->format('Y-m-d'),
            description: "Stock transfer #{$transfer->id} from {$fromWarehouse->name} to {$toWarehouse->name}",
            lines: [
                ['account_id' => $toInventory->id, 'debit' => $totalCost, 'credit' => 0],
                ['account_id' => $fromInventory->id, 'debit' => 0, 'credit' => $totalCost],
            ],
            referenceType: 'transfer',
            referenceId: $transfer->id,
        );
    }

    #[OA\Post(path: "/stock-transfers/{id}/cancel", tags: ["Stock Transfers"], summary: "Cancel a stock transfer", responses: [new OA\Response(response: 200, description: "Transfer cancelled")])]
    public function cancel(string $id): JsonResponse
    {
        $transfer = StockTransfer::findOrFail($id);

        if (!in_array($transfer->status, ['pending', 'in_transit'])) {
            return response()->json([
                'error' => ['code' => 'ERR_INVALID_STATUS', 'message' => 'Only pending or in_transit transfers can be cancelled.'],
            ], 400);
        }

        $transfer->update(['status' => 'cancelled']);
        $transfer->load($this->withRelations);

        return response()->json(['data' => $transfer]);
    }
}
