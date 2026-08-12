<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\PlanDiscount;
use App\Models\Landlord\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class PlanDiscountController extends Controller
{
    #[OA\Get(path: "/admin/discounts", tags: ["Admin Discounts"], summary: "List discounts", responses: [new OA\Response(response: 200, description: "Discounts listed")])]
    public function index(): JsonResponse
    {
        $discounts = PlanDiscount::with('plan')->orderByDesc('created_at')->get();
        return response()->json($discounts);
    }

    #[OA\Post(path: "/admin/discounts", tags: ["Admin Discounts"], summary: "Create discount", responses: [new OA\Response(response: 201, description: "Discount created")])]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'plan_id' => [
                'required',
                function ($attr, $value, $fail) {
                    if (!DB::connection('landlord')->table('subscription_plans')->where('id', $value)->exists()) {
                        $fail('Selected plan does not exist.');
                    }
                },
            ],
            'name' => 'required|string|max:255',
            'code' => [
                'nullable', 'string', 'max:50',
                function ($attr, $value, $fail) {
                    if ($value && DB::connection('landlord')->table('plan_discounts')->where('code', $value)->exists()) {
                        $fail('The code has already been taken.');
                    }
                },
            ],
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'billing_cycle' => 'nullable|in:monthly,yearly',
            'description' => 'nullable|string',
            'is_recurring' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'max_uses' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
        ]);

        $discount = PlanDiscount::create($data);

        AuditLog::log('discount.create', 'billing', 'PlanDiscount', $discount->id, $discount->name);

        return response()->json($discount->load('plan'), 201);
    }

    #[OA\Get(path: "/admin/discounts/{discount}", tags: ["Admin Discounts"], summary: "Get discount", responses: [new OA\Response(response: 200, description: "Discount returned")])]
    public function show(PlanDiscount $discount): JsonResponse
    {
        $discount->load('plan');
        return response()->json($discount);
    }

    #[OA\Put(path: "/admin/discounts/{discount}", tags: ["Admin Discounts"], summary: "Update discount", responses: [new OA\Response(response: 200, description: "Discount updated")])]
    public function update(Request $request, PlanDiscount $discount): JsonResponse
    {
        $old = $discount->toArray();

        $discountId = $discount->id;
        $data = $request->validate([
            'plan_id' => [
                'sometimes',
                function ($attr, $value, $fail) {
                    if (!DB::connection('landlord')->table('subscription_plans')->where('id', $value)->exists()) {
                        $fail('Selected plan does not exist.');
                    }
                },
            ],
            'name' => 'sometimes|string|max:255',
            'code' => [
                'nullable', 'string', 'max:50',
                function ($attr, $value, $fail) use ($discountId) {
                    if ($value && DB::connection('landlord')->table('plan_discounts')->where('code', $value)->where('id', '!=', $discountId)->exists()) {
                        $fail('The code has already been taken.');
                    }
                },
            ],
            'type' => 'sometimes|in:percentage,fixed',
            'value' => 'sometimes|numeric|min:0',
            'billing_cycle' => 'nullable|in:monthly,yearly',
            'description' => 'nullable|string',
            'is_recurring' => 'boolean',
            'valid_from' => 'nullable|date',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'max_uses' => 'nullable|integer|min:1',
            'current_uses' => 'sometimes|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $discount->update($data);

        AuditLog::log('discount.update', 'billing', 'PlanDiscount', $discount->id, $discount->name, $old, $discount->fresh()->toArray());

        return response()->json($discount->fresh()->load('plan'));
    }

    #[OA\Delete(path: "/admin/discounts/{discount}", tags: ["Admin Discounts"], summary: "Delete discount", responses: [new OA\Response(response: 200, description: "Discount deleted")])]
    public function destroy(PlanDiscount $discount): JsonResponse
    {
        if ($discount->subscriptions()->exists()) {
            return response()->json(['error' => 'Cannot delete a discount that is applied to active subscriptions'], 400);
        }

        AuditLog::log('discount.delete', 'billing', 'PlanDiscount', $discount->id, $discount->name);
        $discount->delete();

        return response()->json(['message' => 'Discount deleted']);
    }
}
