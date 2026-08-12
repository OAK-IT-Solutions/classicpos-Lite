<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Grn;
use App\Models\GrnItem;
use App\Models\Inventory;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/grn")]
class GrnController extends BaseController
{
    protected string $modelClass = Grn::class;

    protected array $searchableFields = ['notes'];

    protected array $withRelations = ['purchaseOrder', 'receivedBy'];

    public function __construct(
        protected AccountingService $accountingService,
    ) {}

    protected function rules(Request $request, ?string $id = null): array
    {
        $rules = [
            'purchase_order_id' => 'required|uuid|exists:purchase_orders,id',
            'received_by' => 'required|uuid|exists:users,id',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array',
            'items.*.product_id' => 'required_with:items|uuid|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.001',
            'items.*.unit_cost' => 'required_with:items|numeric|min:0',
            'items.*.batch_number' => 'nullable|string|max:50',
            'items.*.expiry_date' => 'nullable|date',
        ];

        return $rules;
    }

    protected function indexQuery(Request $request)
    {
        $query = parent::indexQuery($request);

        if ($request->filled('purchase_order_id')) {
            $query->where('purchase_order_id', $request->purchase_order_id);
        }

        return $query;
    }

    protected function additionalQuery(Request $request, $query): void
    {
        if ($request->filled('purchase_order_id')) {
            $query->where('purchase_order_id', $request->purchase_order_id);
        }
    }

    #[OA\Post(path: "/grn", tags: ["GRN"], summary: "Create a GRN", responses: [new OA\Response(response: 201, description: "GRN created")])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules($request));

        return DB::transaction(function () use ($validated) {
            $grn = Grn::create([
                'id' => (string) Str::uuid(),
                'purchase_order_id' => $validated['purchase_order_id'],
                'received_by' => $validated['received_by'],
                'notes' => $validated['notes'] ?? null,
            ]);

                if (!empty($validated['items'])) {
                $totalReceived = 0;

                foreach ($validated['items'] as $item) {
                    GrnItem::create([
                        'id' => (string) Str::uuid(),
                        'grn_id' => $grn->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'unit_cost' => $item['unit_cost'],
                        'batch_number' => $item['batch_number'] ?? null,
                        'expiry_date' => $item['expiry_date'] ?? null,
                    ]);

                    $po = $grn->purchaseOrder;
                    $warehouse = Warehouse::where('branch_id', $po->branch_id)
                        ->where('is_active', true)
                        ->first();

                    if ($warehouse) {
                        $inventory = Inventory::firstOrNew(
                            [
                                'product_id' => $item['product_id'],
                                'warehouse_id' => $warehouse->id,
                            ]
                        );

                        if (!$inventory->exists) {
                            $inventory->id = (string) Str::uuid();
                            $inventory->quantity = 0;
                            $inventory->batch_number = $item['batch_number'] ?? null;
                            $inventory->expiry_date = $item['expiry_date'] ?? null;
                            $inventory->save();
                        }

                        $inventory->increment('quantity', (float) $item['quantity']);
                        $inventory->refresh();

                        StockMovement::create([
                            'id' => (string) Str::uuid(),
                            'inventory_id' => $inventory->id,
                            'product_id' => $item['product_id'],
                            'warehouse_id' => $warehouse->id,
                            'quantity_change' => (float) $item['quantity'],
                            'running_balance' => $inventory->quantity,
                            'reference_type' => 'grn',
                            'reference_id' => $grn->id,
                        ]);

                        $totalReceived += $item['quantity'] * $item['unit_cost'];
                    }
                }

                $po->update(['total_amount' => $totalReceived]);
            } else {
                $totalReceived = 0;
            }

            $this->createGrnJournalEntry($grn, $totalReceived);

            $grn->purchaseOrder()->update(['status' => 'received']);
            $grn->load($this->withRelations);

            return response()->json(['data' => $grn], 201);
        });
    }

    private function createGrnJournalEntry(Grn $grn, float $totalCost = 0): void
    {
        if ($totalCost <= 0) {
            return;
        }

        try {
            $this->accountingService->createJournalEntry(
                branchId: $grn->purchaseOrder->branch_id,
                entryDate: now()->format('Y-m-d'),
                description: "GRN for PO #{$grn->purchaseOrder_id}",
                lines: [
                    [
                        'account_code' => '1330',
                        'debit' => $totalCost,
                        'credit' => 0,
                        'description' => 'Inventory received',
                    ],
                    [
                        'account_code' => '2100',
                        'debit' => 0,
                        'credit' => $totalCost,
                        'description' => 'Accounts payable',
                    ],
                ],
                referenceType: 'grn',
                referenceId: $grn->id,
            );
        } catch (\Exception $e) {
            Log::error('Failed to create GRN journal entry', [
                'grn_id' => $grn->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
