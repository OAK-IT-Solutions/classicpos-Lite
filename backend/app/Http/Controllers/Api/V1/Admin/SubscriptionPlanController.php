<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\SubscriptionFeature;
use App\Models\Landlord\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

class SubscriptionPlanController extends Controller
{
    #[OA\Get(path: "/admin/plans", tags: ["Admin Plans"], summary: "List subscription plans", responses: [new OA\Response(response: 200, description: "Plans listed")])]
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::with('planFeatures')
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->get();
        return response()->json($plans);
    }

    #[OA\Post(path: "/admin/plans", tags: ["Admin Plans"], summary: "Create subscription plan", responses: [new OA\Response(response: 201, description: "Plan created")])]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => [
                'required', 'string', 'max:100',
                function ($attr, $value, $fail) {
                    if (DB::connection('landlord')->table('subscription_plans')->where('slug', $value)->exists()) {
                        $fail('The slug has already been taken.');
                    }
                },
            ],
            'description' => 'nullable|string',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'discount_percent_yearly' => 'nullable|numeric|min:0|max:100',
            'max_branches' => 'required|integer|min:-1',
            'max_users_per_branch' => 'required|integer|min:-1',
            'max_devices_per_branch' => 'required|integer|min:-1',
            'features' => 'nullable|array',
            'features.*' => 'string',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => [
                function ($attr, $value, $fail) {
                    if (!DB::connection('landlord')->table('subscription_features')->where('id', $value)->exists()) {
                        $fail('Selected feature does not exist.');
                    }
                },
            ],
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_popular' => 'boolean',
            'highlight_color' => 'nullable|string|max:20',
            'cta_text' => 'nullable|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        if (!empty($data['is_default'])) {
            SubscriptionPlan::where('is_default', true)->update(['is_default' => false]);
        }

        $plan = SubscriptionPlan::create($data);

        if (!empty($data['feature_ids'])) {
            $sync = [];
            foreach ($data['feature_ids'] as $i => $fid) {
                $sync[$fid] = ['sort_order' => $i];
            }
            $plan->planFeatures()->sync($sync);
        }

        AuditLog::log('plan.create', 'billing', 'SubscriptionPlan', $plan->id, $plan->name);

        return response()->json($plan->fresh()->load('planFeatures'), 201);
    }

    #[OA\Get(path: "/admin/plans/{plan}", tags: ["Admin Plans"], summary: "Get subscription plan", responses: [new OA\Response(response: 200, description: "Plan returned")])]
    public function show(SubscriptionPlan $plan): JsonResponse
    {
        $plan->loadCount('subscriptions');
        return response()->json($plan);
    }

    #[OA\Put(path: "/admin/plans/{plan}", tags: ["Admin Plans"], summary: "Update subscription plan", responses: [new OA\Response(response: 200, description: "Plan updated")])]
    public function update(Request $request, SubscriptionPlan $plan): JsonResponse
    {
        $old = $plan->toArray();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'price_monthly' => 'sometimes|numeric|min:0',
            'price_yearly' => 'sometimes|numeric|min:0',
            'discount_percent_yearly' => 'nullable|numeric|min:0|max:100',
            'max_branches' => 'sometimes|integer|min:-1',
            'max_users_per_branch' => 'sometimes|integer|min:-1',
            'max_devices_per_branch' => 'sometimes|integer|min:-1',
            'features' => 'nullable|array',
            'feature_ids' => 'nullable|array',
            'feature_ids.*' => [
                function ($attr, $value, $fail) {
                    if (!DB::connection('landlord')->table('subscription_features')->where('id', $value)->exists()) {
                        $fail('Selected feature does not exist.');
                    }
                },
            ],
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_popular' => 'boolean',
            'highlight_color' => 'nullable|string|max:20',
            'cta_text' => 'nullable|string|max:100',
            'sort_order' => 'integer|min:0',
        ]);

        if (!empty($data['is_default'])) {
            SubscriptionPlan::where('is_default', true)->where('id', '!=', $plan->id)->update(['is_default' => false]);
        }

        $plan->update($data);

        if ($request->has('feature_ids')) {
            $sync = [];
            foreach ($data['feature_ids'] as $i => $fid) {
                $sync[$fid] = ['sort_order' => $i];
            }
            $plan->planFeatures()->sync($sync);
        }

        AuditLog::log('plan.update', 'billing', 'SubscriptionPlan', $plan->id, $plan->name, $old, $plan->fresh()->toArray());

        return response()->json($plan->fresh()->load('planFeatures'));
    }

    #[OA\Delete(path: "/admin/plans/{plan}", tags: ["Admin Plans"], summary: "Delete subscription plan", responses: [new OA\Response(response: 200, description: "Plan deleted")])]
    public function destroy(SubscriptionPlan $plan): JsonResponse
    {
        if ($plan->subscriptions()->whereIn('status', ['active', 'trialing'])->exists()) {
            return response()->json(['error' => 'Cannot delete a plan with active subscriptions'], 400);
        }

        AuditLog::log('plan.delete', 'billing', 'SubscriptionPlan', $plan->id, $plan->name);
        $plan->delete();

        return response()->json(['message' => 'Plan deleted']);
    }
}
