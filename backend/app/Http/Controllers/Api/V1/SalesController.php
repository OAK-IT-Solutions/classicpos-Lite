<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaleRequest;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class SalesController extends Controller
{
    public function __construct(
        protected PosService $posService,
    ) {}

    #[OA\Get(path: "/sales", tags: ["Sales"], summary: "List sales", responses: [new OA\Response(response: 200, description: "Paginated sales")])]
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'nullable|uuid|exists:branches,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d',
            'search'    => 'nullable|string|max:255',
            'status'    => 'nullable|string|in:completed,pending,cancelled,voided,pending_sync',
            'payment_method' => 'nullable|string|in:cash,mobile_money,card,qr',
            'customer_id' => 'nullable|uuid|exists:customers,id',
            'min_amount' => 'nullable|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ]);

        return response()->json($this->posService->getSalesList($validated));
    }

    public function store(SaleRequest $request): JsonResponse
    {
        try {
            $result = $this->posService->createSale($request->validated());

            return response()->json($result, 201);
        } catch (\RuntimeException $e) {
            Log::warning('Sale creation failed', [
                'branch_id' => $request->input('branch_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'ERR_SALE_FAILED',
                    'message' => $e->getMessage(),
                    'timestamp' => now()->toISOString(),
                ],
            ], 400);
        } catch (\Exception $e) {
            Log::error('Unexpected sale error', [
                'branch_id' => $request->input('branch_id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => [
                    'code' => 'ERR_INTERNAL',
                    'message' => 'An unexpected error occurred while processing the sale.',
                    'timestamp' => now()->toISOString(),
                ],
            ], 500);
        }
    }

    public function show(string $saleId): JsonResponse
    {
        return response()->json($this->posService->getSaleDetail($saleId));
    }

    public function void(string $saleId): JsonResponse
    {
        try {
            $this->posService->voidSale($saleId);

            return response()->json(['message' => 'Sale voided successfully.']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_VOID_FAILED',
                    'message' => $e->getMessage(),
                    'timestamp' => now()->toISOString(),
                ],
            ], 400);
        }
    }

    public function emailInvoice(string $saleId): JsonResponse
    {
        try {
            $this->posService->emailInvoice($saleId);

            return response()->json(['message' => 'Invoice emailed successfully.']);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_EMAIL_FAILED',
                    'message' => $e->getMessage(),
                    'timestamp' => now()->toISOString(),
                ],
            ], 400);
        }
    }
}
