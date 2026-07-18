<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\PosProductService;
use App\Services\PosService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class PosController extends Controller
{
    public function __construct(
        protected PosProductService $posProductService,
        protected PosService $posService,
    ) {}

    #[OA\Get(path: "/pos/products", tags: ["POS"], summary: "Get POS products", responses: [new OA\Response(response: 200, description: "POS products with stock info")])]
    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'category' => 'nullable|string|max:255',
            'search' => 'nullable|string|max:255',
        ]);

        $result = $this->posProductService->getProducts(
            $validated['branch_id'],
            $validated['category'] ?? null,
            $validated['search'] ?? null,
        );

        return response()->json($result);
    }

    #[OA\Post(path: "/pos/hold", tags: ["POS"], summary: "Hold sale", responses: [new OA\Response(response: 201, description: "Sale held")])]
    public function hold(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'cart_data' => 'required|array',
            'customer_id' => 'nullable|exists:customers,id',
            'promo_code' => 'nullable|string|max:50',
            'tax_profile_id' => 'nullable|exists:tax_profiles,id',
            'loyalty_points_redeemed' => 'nullable|integer|min:0',
            'note' => 'nullable|string|max:500',
        ]);

        $validated['user_id'] = $request->user()->id;

        $holdSale = $this->posService->holdSale($validated);

        return response()->json([
            'message' => 'Sale held successfully.',
            'data' => $holdSale,
        ], 201);
    }

    public function held(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'required|exists:branches,id',
        ]);

        $heldSales = $this->posService->getHeldSales(
            $request->input('branch_id'),
            $request->user()->id,
        );

        return response()->json(['data' => $heldSales]);
    }

    public function resume(string $id): JsonResponse
    {
        try {
            $data = $this->posService->resumeSale($id);

            return response()->json(['data' => $data]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_RESUME_FAILED',
                    'message' => 'Could not resume sale.',
                    'timestamp' => now()->toISOString(),
                ],
            ], 404);
        }
    }
}
