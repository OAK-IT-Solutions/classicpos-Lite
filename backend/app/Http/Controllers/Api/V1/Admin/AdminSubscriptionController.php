<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\PlanDiscount;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AdminSubscriptionController extends Controller
{
    #[OA\Get(path: "/admin/subscriptions", tags: ["Admin Subscriptions"], summary: "List subscriptions", responses: [new OA\Response(response: 200, description: "Subscriptions listed")])]
    public function index(Request $request): JsonResponse
    {
        $query = Subscription::with('tenant', 'plan')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->plan_id, fn ($q, $p) => $q->where('plan_id', $p));

        $subscriptions = $query->orderByDesc('created_at')->paginate($request->per_page ?? 20);

        return response()->json($subscriptions);
    }

    #[OA\Get(path: "/admin/subscriptions/{subscription}", tags: ["Admin Subscriptions"], summary: "Get subscription", responses: [new OA\Response(response: 200, description: "Subscription returned")])]
    public function show(Subscription $subscription): JsonResponse
    {
        $subscription->load('tenant', 'plan', 'paymentTransactions');
        return response()->json($subscription);
    }

    #[OA\Post(path: "/admin/subscriptions/{subscription}/change-plan", tags: ["Admin Subscriptions"], summary: "Change subscription plan", responses: [new OA\Response(response: 200, description: "Plan changed")])]
    public function changePlan(Request $request, Subscription $subscription): JsonResponse
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
            'billing_cycle' => 'sometimes|in:monthly,yearly',
            'discount_id' => [
                'nullable',
                function ($attr, $value, $fail) {
                    if ($value && !DB::connection('landlord')->table('plan_discounts')->where('id', $value)->exists()) {
                        $fail('Selected discount does not exist.');
                    }
                },
            ],
        ]);

        $old = $subscription->toArray();

        $plan = \App\Models\Landlord\SubscriptionPlan::find($data['plan_id']);
        $cycle = $data['billing_cycle'] ?? $subscription->billing_cycle;
        $amount = $plan?->getPriceForCycle($cycle) ?? 0;
        $originalAmount = $plan?->getPriceForCycle($cycle) ?? 0;
        $discountPercent = null;
        $discountId = null;

        if (!empty($data['discount_id'])) {
            $discount = PlanDiscount::find($data['discount_id']);
            if ($discount && $discount->isValid()) {
                $discounted = $discount->applyTo($amount);
                $discountPercent = $discount->type === 'percentage' ? $discount->value
                    : round((1 - $discounted / $amount) * 100, 2);
                $amount = $discounted;
                $discountId = $discount->id;
            }
        }

        $subscription->update([
            'plan_id' => $data['plan_id'],
            'billing_cycle' => $cycle,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addMonths(1),
            'cancelled_at' => null,
            'amount' => $amount,
            'original_amount' => $originalAmount,
            'discount_id' => $discountId,
            'discount_percent' => $discountPercent,
        ]);

        AuditLog::log(
            'subscription.change_plan',
            'billing',
            'Subscription',
            $subscription->id,
            "Plan changed for {$subscription->tenant->name}",
            $old,
            $subscription->fresh()->toArray()
        );

        return response()->json($subscription->fresh()->load('tenant', 'plan'));
    }

    #[OA\Post(path: "/admin/subscriptions/{subscription}/cancel", tags: ["Admin Subscriptions"], summary: "Cancel subscription", responses: [new OA\Response(response: 200, description: "Subscription cancelled")])]
    public function cancel(Subscription $subscription): JsonResponse
    {
        $old = $subscription->toArray();

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        AuditLog::log(
            'subscription.cancel',
            'billing',
            'Subscription',
            $subscription->id,
            "Subscription cancelled for {$subscription->tenant->name}",
            $old,
            ['status' => 'cancelled']
        );

        return response()->json($subscription->fresh()->load('tenant', 'plan'));
    }
}
