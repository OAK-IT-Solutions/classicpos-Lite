<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\ReturnApproved;
use App\Models\OperatingAccount;
use App\Models\OrderReturn;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Services\AccountingService;
use App\Services\InventoryService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Returns', description: 'Sales return management, approval, and refund processing')]
class ReturnController extends BaseController
{
    protected string $modelClass = OrderReturn::class;

    protected array $searchableFields = ['reason', 'status'];

    protected array $withRelations = ['sale', 'branch', 'items.product'];

    public function __construct(
        protected InventoryService $inventoryService,
        protected PaymentService $paymentService,
        protected AccountingService $accountingService,
    ) {}

    protected function rules(Request $request, ?string $id = null): array
    {
        if ($id) {
            return [
                'status' => 'sometimes|required|string|in:pending,approved,rejected',
                'reason' => 'nullable|string|max:1000',
            ];
        }

        return [
            'sale_id' => 'required|uuid|exists:sales,id',
            'branch_id' => 'required|uuid|exists:branches,id',
            'reason' => 'nullable|string|max:1000',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|uuid|exists:products,id',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.reason' => 'nullable|string|max:500',
            'items.*.condition' => 'nullable|string|in:returnable,damaged,defect',
        ];
    }

    #[OA\Post(
        path: '/api/v1/returns',
        tags: ['Returns'],
        summary: 'Create a sales return',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'sale_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'branch_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'reason', type: 'string'),
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'product_id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'quantity', type: 'number'),
                new OA\Property(property: 'reason', type: 'string'),
                new OA\Property(property: 'condition', type: 'string', enum: ['returnable', 'damaged', 'defect']),
            ])),
        ], required: ['sale_id', 'branch_id', 'items'])),
        responses: [
            new OA\Response(response: 201, description: 'Return created'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules($request));
        $items = $validated['items'];
        unset($validated['items']);

        $validated['status'] = 'pending';
        $validated['refund_amount'] = 0;

        $return = DB::transaction(function () use ($validated, $items) {
            $return = OrderReturn::create($validated);

            foreach ($items as $item) {
                ReturnItem::create([
                    'return_id' => $return->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'reason' => $item['reason'] ?? null,
                ]);
            }

            return $return;
        });

        $return->load($this->withRelations);

        return response()->json(['data' => $return], 201);
    }

    #[OA\Post(
        path: '/api/v1/returns/{id}/approve',
        tags: ['Returns'],
        summary: 'Approve a pending return and process refund',
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))],
        responses: [
            new OA\Response(response: 200, description: 'Return approved'),
            new OA\Response(response: 400, description: 'Invalid return status'),
        ]
    )]
    public function approve(string $id): JsonResponse
    {
        $return = OrderReturn::with('items.product')->findOrFail($id);

        if ($return->status !== 'pending') {
            return response()->json([
                'error' => [
                    'code' => 'ERR_INVALID_RETURN_STATUS',
                    'message' => 'Only pending returns can be approved.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }

        try {
            DB::transaction(function () use ($return) {
                $refundAmount = 0;

                foreach ($return->items as $item) {
                    if ($item->condition === 'returnable') {
                        $this->inventoryService->restock(
                            $item->product_id,
                            $return->branch_id,
                            $item->quantity,
                            'return',
                            $return->id,
                        );
                        $refundAmount += $item->quantity * ($item->product?->price ?? 0);
                    } else {
                        // Damaged/defect items: no restock, no refund value
                        Log::info('Non-returnable item skipped from restock', [
                            'return_id' => $return->id,
                            'product_id' => $item->product_id,
                            'condition' => $item->condition,
                        ]);
                    }
                }

                // Only attempt refund if sale has a completed payment
                $sale = \App\Models\Sale::find($return->sale_id);
                $hasPayment = $sale && $sale->payments()->where('status', Payment::STATUS_COMPLETED)->exists();
                $refundPaymentId = null;

                if ($hasPayment && $refundAmount > 0) {
                    $refundResult = $this->paymentService->refund(
                        $return->sale_id,
                        $refundAmount,
                        $return->id,
                    );

                    if (!$refundResult['success']) {
                        Log::warning('Refund skipped for return', [
                            'return_id' => $return->id,
                            'error' => $refundResult['error'],
                        ]);
                    } else {
                        $refundPaymentId = $refundResult['refund_payment_id'] ?? null;
                    }
                } else {
                    Log::info('No payment to refund for return', [
                        'return_id' => $return->id,
                        'sale_id' => $return->sale_id,
                        'has_payment' => $hasPayment,
                        'refund_amount' => $refundAmount,
                    ]);
                }

                $this->createReturnJournalEntry($return, $refundAmount);

                $return->update([
                    'status' => 'approved',
                    'refund_amount' => $refundAmount,
                    'refund_payment_id' => $refundPaymentId,
                    'refunded_at' => now(),
                ]);
            });

            $return->load($this->withRelations);

            ReturnApproved::dispatch($return->fresh()->load($this->withRelations));

            return response()->json(['data' => $return]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_RETURN_APPROVE_FAILED',
                    'message' => $e->getMessage(),
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }
    }

    private function createReturnJournalEntry(OrderReturn $return, float $refundAmount): void
    {
        if ($refundAmount <= 0) {
            return;
        }

        $defaultOp = OperatingAccount::where('branch_id', $return->branch_id)
            ->where('is_default', true)
            ->first();

        if (!$defaultOp) {
            Log::warning('No default operating account, skipping return journal entry', ['branch_id' => $return->branch_id]);
            return;
        }

        $lines = [
            [
                'account_code' => '4310',
                'debit' => $refundAmount,
                'credit' => 0,
                'description' => 'Sales returns',
            ],
            [
                'account_id' => $defaultOp->account_id,
                'debit' => 0,
                'credit' => $refundAmount,
                'description' => 'Refund payment',
            ],
        ];

        $totalRestockCost = 0;
        foreach ($return->items as $item) {
            if ($item->condition === 'returnable' && $item->product) {
                $cost = (float) ($item->product->cost ?? 0) * (float) $item->quantity;
                if ($cost > 0) {
                    $totalRestockCost += $cost;
                }
            }
        }

        if ($totalRestockCost > 0) {
            $lines[] = [
                'account_code' => '1330',
                'debit' => $totalRestockCost,
                'credit' => 0,
                'description' => 'Restocked inventory',
            ];
            $lines[] = [
                'account_code' => '5100',
                'debit' => 0,
                'credit' => $totalRestockCost,
                'description' => 'COGS reversal',
            ];
        }

        try {
            $this->accountingService->createJournalEntry(
                branchId: $return->branch_id,
                entryDate: now()->format('Y-m-d'),
                description: "Return #{$return->id}",
                lines: $lines,
                referenceType: 'return',
                referenceId: $return->id,
            );
        } catch (\Exception $e) {
            Log::error('Failed to create return journal entry', [
                'return_id' => $return->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
