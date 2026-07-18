<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/purchase-orders")]
class PurchaseOrderController extends BaseController
{
    protected string $modelClass = PurchaseOrder::class;

    protected array $searchableFields = ['po_number', 'notes'];

    protected array $withRelations = ['supplier', 'branch', 'items.product'];

    protected function rules(Request $request, ?string $id = null): array
    {
        if ($id) {
            return [
                'status' => 'sometimes|in:draft,pending,approved,received,cancelled',
                'total_amount' => 'sometimes|numeric|min:0',
                'notes' => 'nullable|string',
            ];
        }

        return [
            'supplier_id' => 'required|uuid|exists:suppliers,id',
            'branch_id' => 'required|uuid|exists:branches,id',
            'po_number' => 'required|string|max:50|unique:purchase_orders,po_number',
            'notes' => 'nullable|string',
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|uuid|exists:products,id',
            'items.*.quantity' => 'required_with:items|numeric|min:0.001',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ];
    }

    #[OA\Post(path: "/purchase-orders", tags: ["Purchase Orders"], summary: "Create a purchase order", responses: [new OA\Response(response: 201, description: "Purchase order created")])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules($request));
        $items = $validated['items'] ?? [];
        unset($validated['items']);
        $validated['status'] = 'draft';

        $po = DB::transaction(function () use ($validated, $items) {
            $po = PurchaseOrder::create($validated);

            foreach ($items as $item) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $item['unit_cost'] ?? 0,
                ]);
            }

            if (!empty($items)) {
                $total = collect($items)->sum(fn ($i) => ($i['unit_cost'] ?? 0) * $i['quantity']);
                $po->update(['total_amount' => $total]);
            }

            return $po;
        });

        $po->load($this->withRelations);

        return response()->json(['data' => $po], 201);
    }

    #[OA\Post(path: "/purchase-orders/{id}/transition", tags: ["Purchase Orders"], summary: "Transition PO status", responses: [new OA\Response(response: 200, description: "Status updated")])]
    public function transitionStatus(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:approved,received,cancelled',
        ]);

        $po = PurchaseOrder::findOrFail($id);
        $newStatus = $validated['status'];

        $allowedTransitions = [
            'draft' => ['pending', 'cancelled'],
            'pending' => ['approved', 'cancelled'],
            'approved' => ['received', 'cancelled'],
        ];

        if (!isset($allowedTransitions[$po->status]) || !in_array($newStatus, $allowedTransitions[$po->status])) {
            return response()->json([
                'error' => ['code' => 'ERR_INVALID_TRANSITION', 'message' => "Cannot transition from {$po->status} to {$newStatus}."],
            ], 400);
        }

        $po->update(['status' => $newStatus]);
        $po->load($this->withRelations);

        Log::info('PO status changed', ['po_id' => $id, 'from' => $po->getOriginal('status'), 'to' => $newStatus]);

        return response()->json(['data' => $po]);
    }
}
