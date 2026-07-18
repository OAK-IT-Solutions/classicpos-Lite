<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Subscription;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/subscriptions")]
class SubscriptionController extends Controller
{
    public function __construct(
        protected SubscriptionService $subscriptionService
    ) {}

    #[OA\Get(path: "/subscriptions", tags: ["Subscriptions"], summary: "List subscriptions", responses: [new OA\Response(response: 200, description: "Subscription list")])]
    public function index(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $branch = Branch::with('subscription')->findOrFail($branchId);

        return response()->json([
            'data' => $branch->subscription,
        ]);
    }

    #[OA\Get(path: "/subscriptions/current", tags: ["Subscriptions"], summary: "Get current subscription with limits", responses: [new OA\Response(response: 200, description: "Current subscription details")])]
    public function current(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $branch = Branch::with('subscription')->findOrFail($branchId);

        $subscription = $branch->subscription;
        $plans = $this->subscriptionService->getAvailablePlans();
        $planType = $subscription ? $subscription->plan_type : 'starter';

        return response()->json([
            'data' => [
                'subscription' => $subscription,
                'plan' => $plans[$planType] ?? null,
                'is_active' => $subscription ? $this->subscriptionService->isActive($branch) : false,
                'limits' => [
                    'can_create_branch' => $this->subscriptionService->canCreateBranch($branch),
                    'can_create_user' => $this->subscriptionService->canCreateUser($branch),
                    'can_create_device' => $this->subscriptionService->canCreateDevice($branch),
                ],
            ],
        ]);
    }

    #[OA\Get(path: "/subscriptions/{id}", tags: ["Subscriptions"], summary: "Get a subscription", responses: [new OA\Response(response: 200, description: "Subscription details")])]
    public function show(string $id)
    {
        $subscription = Subscription::with('branch')->findOrFail($id);

        return response()->json([
            'data' => $subscription,
        ]);
    }

    #[OA\Put(path: "/subscriptions", tags: ["Subscriptions"], summary: "Update subscription plan", responses: [new OA\Response(response: 200, description: "Subscription updated")])]
    public function update(Request $request)
    {
        $validated = $request->validate([
            'plan_type' => 'required|string|in:starter,standard,premium',
            'billing_cycle' => 'sometimes|string|in:monthly,annual',
        ]);

        $branchId = $request->user()->branch_id;
        $branch = Branch::findOrFail($branchId);
        $subscription = $branch->subscription;

        if (!$subscription) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_NO_SUBSCRIPTION',
                    'message' => 'No subscription found for this branch.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 404);
        }

        $this->subscriptionService->changePlan($subscription, $validated['plan_type']);

        if (isset($validated['billing_cycle'])) {
            $subscription->update(['billing_cycle' => $validated['billing_cycle']]);
        }

        return response()->json([
            'data' => $subscription->fresh()->load('branch'),
            'message' => 'Subscription updated successfully.',
        ]);
    }

    #[OA\Post(path: "/subscriptions/cancel", tags: ["Subscriptions"], summary: "Cancel subscription", responses: [new OA\Response(response: 200, description: "Subscription cancelled")])]
    public function cancel(Request $request)
    {
        $branchId = $request->user()->branch_id;
        $branch = Branch::findOrFail($branchId);
        $subscription = $branch->subscription;

        if (!$subscription) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_NO_SUBSCRIPTION',
                    'message' => 'No subscription found for this branch.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 404);
        }

        $this->subscriptionService->cancel($subscription);

        return response()->json([
            'data' => $subscription->fresh(),
            'message' => 'Subscription cancelled. It will remain active for 30 days.',
        ]);
    }

    #[OA\Get(path: "/subscriptions/plans", tags: ["Subscriptions"], summary: "List available plans", responses: [new OA\Response(response: 200, description: "Available plans")])]
    public function availablePlans()
    {
        return response()->json([
            'data' => $this->subscriptionService->getAvailablePlans(),
        ]);
    }
}
