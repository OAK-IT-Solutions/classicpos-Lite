<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Sync;
use App\Services\PosService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/sync/sales")]
class SyncSalesController extends Controller
{
    public function __construct(
        protected PosService $posService,
        protected PaymentService $paymentService,
    ) {}

    #[OA\Post(path: "/sync/sales", tags: ["Sync"], summary: "Sync offline sales", responses: [new OA\Response(response: 200, description: "Sync results")])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sales' => 'required|array|min:1|max:500',
            'sales.*.local_id' => 'required|string|max:64',
            'sales.*.branch_id' => 'required|uuid|exists:branches,id',
            'sales.*.customer_id' => 'nullable|uuid|exists:customers,id',
            'sales.*.items' => 'required|array|min:1',
            'sales.*.items.*.product_id' => 'required|uuid|exists:products,id',
            'sales.*.items.*.product_name' => 'nullable|string|max:255',
            'sales.*.items.*.quantity' => 'required|numeric|min:0.001',
            'sales.*.items.*.price' => 'required|numeric|min:0',
            'sales.*.payment_method' => 'required|string|in:cash,mobile_money,card,qr',
            'sales.*.gateway' => 'nullable|string|max:50',
            'sales.*.promo_code' => 'nullable|string|max:50',
            'sales.*.tax_profile_id' => 'nullable|uuid|exists:tax_profiles,id',
            'sales.*.loyalty_points_redeemed' => 'nullable|integer|min:0',
            'sales.*.subtotal' => 'required|numeric|min:0',
            'sales.*.discount' => 'nullable|numeric|min:0',
            'sales.*.tax_amount' => 'nullable|numeric|min:0',
            'sales.*.total_amount' => 'required|numeric|min:0',
            'sales.*.cash_received' => 'nullable|numeric|min:0',
            'sales.*.change_due' => 'nullable|numeric|min:0',
            'sales.*.created_offline_at' => 'nullable|date',
        ]);

        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($validated['sales'] as $saleData) {
            $localId = $saleData['local_id'];

            // Idempotency: check if this local_id has already been processed
            $existingSync = Sync::where('table_name', 'sales')
                ->where('record_id', $localId)
                ->where('status', 'synced')
                ->first();

            if ($existingSync && $existingSync->payload) {
                $payload = $existingSync->payload;
                $results[] = [
                    'local_id' => $localId,
                    'success' => true,
                    'server_id' => $payload['server_id'] ?? null,
                    'invoice_number' => $payload['invoice_number'] ?? null,
                    'duplicate' => true,
                ];
                $successCount++;
                continue;
            }

            try {
                DB::beginTransaction();

                $createData = [
                    'branch_id' => $saleData['branch_id'],
                    'customer_id' => $saleData['customer_id'] ?? null,
                    'items' => array_map(function ($item) {
                        return [
                            'product_id' => $item['product_id'],
                            'quantity' => $item['quantity'],
                            'price' => $item['price'],
                        ];
                    }, $saleData['items']),
                    'payment_method' => $saleData['payment_method'],
                    'gateway' => $saleData['gateway'] ?? null,
                    'promo_code' => $saleData['promo_code'] ?? null,
                    'tax_profile_id' => $saleData['tax_profile_id'] ?? null,
                    'loyalty_points_redeemed' => $saleData['loyalty_points_redeemed'] ?? 0,
                ];

                $sale = $this->posService->createSale($createData);

                // Process payment
                $paymentResult = $this->paymentService->processPayment([
                    'sale_id' => $sale['sale_id'],
                    'amount' => $saleData['total_amount'],
                    'method' => $saleData['payment_method'],
                    'gateway' => $saleData['gateway'] ?? null,
                ]);

                // Log sync record
                Sync::create([
                    'branch_id' => $saleData['branch_id'],
                    'table_name' => 'sales',
                    'record_id' => $localId,
                    'action' => 'create',
                    'payload' => [
                        'server_id' => $sale['sale_id'],
                        'invoice_number' => $sale['invoice_number'],
                        'synced_at' => now()->toISOString(),
                    ],
                    'status' => 'synced',
                ]);

                DB::commit();

                $results[] = [
                    'local_id' => $localId,
                    'success' => true,
                    'server_id' => $sale['sale_id'],
                    'invoice_number' => $sale['invoice_number'],
                    'duplicate' => false,
                ];
                $successCount++;
            } catch (\Exception $e) {
                DB::rollBack();
                Log::warning('Offline sale sync failed', [
                    'local_id' => $localId,
                    'error' => $e->getMessage(),
                ]);

                $results[] = [
                    'local_id' => $localId,
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
                $failureCount++;
            }
        }

        return response()->json([
            'results' => $results,
            'summary' => [
                'total' => count($validated['sales']),
                'succeeded' => $successCount,
                'failed' => $failureCount,
            ],
            'synced_at' => now()->toISOString(),
        ]);
    }
}
