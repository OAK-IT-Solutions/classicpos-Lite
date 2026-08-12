<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\LoyaltyRule;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/loyalty")]
class LoyaltyController extends BaseController
{
    protected string $modelClass = LoyaltyRule::class;

    protected function rules(Request $request, ?string $id = null): array
    {
        return [
            'points_per_amount' => 'required|numeric|min:0.01',
            'points_earned' => 'required|integer|min:1',
            'signup_bonus_points' => 'nullable|integer|min:0',
            'member_levels' => 'nullable|array',
            'reward_thresholds' => 'nullable|array',
            'is_active' => 'boolean',
        ];
    }

    #[OA\Get(path: "/loyalty/current", tags: ["Loyalty"], summary: "Get current loyalty rules", responses: [new OA\Response(response: 200, description: "Active loyalty rule")])]
    public function current(): JsonResponse
    {
        $rule = LoyaltyRule::where('is_active', true)->first();

        if (!$rule) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => $rule]);
    }

    #[OA\Get(path: "/loyalty/points", tags: ["Loyalty"], summary: "Get customer loyalty points", responses: [new OA\Response(response: 200, description: "Customer points")])]
    public function customerPoints(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
        ]);

        $customer = Customer::findOrFail($request->customer_id);

        return response()->json([
            'data' => [
                'customer_id' => $customer->id,
                'name' => $customer->name,
                'loyalty_points' => $customer->loyalty_points,
                'member_level' => $customer->member_level,
            ],
        ]);
    }

    #[OA\Post(path: "/loyalty/redeem", tags: ["Loyalty"], summary: "Redeem loyalty points", responses: [new OA\Response(response: 200, description: "Points redeemed")])]
    public function redeem(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
            'points' => 'required|integer|min:1',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);

        if ($customer->loyalty_points < $validated['points']) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_INSUFFICIENT_POINTS',
                    'message' => 'Customer does not have enough loyalty points.',
                    'details' => [
                        'available: ' . $customer->loyalty_points,
                        'requested: ' . $validated['points'],
                    ],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }

        $rule = LoyaltyRule::where('is_active', true)->first();
        $rewardValue = 0;

        if ($rule && $rule->reward_thresholds) {
            foreach ($rule->reward_thresholds as $threshold) {
                if (($threshold['points'] ?? 0) === (int) $validated['points']) {
                    $rewardValue = $threshold['value'] ?? 0;
                    break;
                }
            }
        }

        $customer->decrement('loyalty_points', $validated['points']);

        return response()->json([
            'data' => [
                'customer_id' => $customer->id,
                'points_redeemed' => $validated['points'],
                'reward_value' => $rewardValue,
                'remaining_points' => $customer->fresh()->loyalty_points,
            ],
        ]);
    }
}
