<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\PlanDiscount;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\Tenant;
use App\Services\PesapalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/billing")]
class BillingController extends Controller
{
    public function __construct(
        private PesapalService $pesapal,
    ) {}

    /**
     * Create a checkout session and return Pesapal iframe URL.
     */
    #[OA\Post(path: "/billing/checkout", tags: ["Billing"], summary: "Create checkout session", responses: [new OA\Response(response: 200, description: "Checkout URL returned")])]
    public function checkout(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $validated = $request->validate([
            'plan_id' => 'required|string',
            'billing_cycle' => 'required|in:monthly,yearly',
            'discount_code' => 'nullable|string|max:50',
        ]);

        $plan = SubscriptionPlan::where('is_active', true)->findOrFail($validated['plan_id']);

        $amount = $validated['billing_cycle'] === 'yearly'
            ? $plan->getPriceForCycle('yearly')
            : $plan->price_monthly;

        $originalAmount = $amount;
        $discountPercent = null;
        $discountId = null;

        // Apply discount code if provided
        if (!empty($validated['discount_code'])) {
            $discount = PlanDiscount::where('code', $validated['discount_code'])
                ->where('plan_id', $plan->id)
                ->where('is_active', true)
                ->first();

            if ($discount && $discount->isValid()) {
                if (!$discount->billing_cycle || $discount->billing_cycle === $validated['billing_cycle']) {
                    $discounted = $discount->applyTo($amount);
                    $discountPercent = $discount->type === 'percentage' ? $discount->value
                        : round((1 - $discounted / $amount) * 100, 2);
                    $amount = $discounted;
                    $discountId = $discount->id;
                    $discount->increment('current_uses');
                }
            }
        }

        $orderId = 'SUB-' . strtoupper(Str::random(12));

        // Create pending transaction
        $transaction = PaymentTransaction::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => null,
            'type' => 'subscription',
            'amount' => $amount,
            'currency' => config('pesapal.currency'),
            'gateway' => 'pesapal',
            'gateway_ref' => $orderId,
            'status' => 'pending',
            'metadata' => [
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'plan_name' => $plan->name,
                'original_amount' => $originalAmount,
                'discount_id' => $discountId,
                'discount_percent' => $discountPercent,
            ],
        ]);

        // Find existing pending/trialing subscription or create new one
        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', ['pending', 'trialing'])
            ->first();

        if (!$subscription) {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'status' => 'pending',
                'starts_at' => now(),
                'amount' => $amount,
                'original_amount' => $originalAmount,
                'discount_id' => $discountId,
                'discount_percent' => $discountPercent,
            ]);
        } else {
            $subscription->update([
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'amount' => $amount,
                'original_amount' => $originalAmount,
                'discount_id' => $discountId,
                'discount_percent' => $discountPercent,
            ]);
        }

        $transaction->update(['subscription_id' => $subscription->id]);

        // Get Pesapal checkout URL
        $checkout = $this->pesapal->submitOrder([
            'order_id' => $orderId,
            'amount' => $amount,
            'description' => "ClassicPOS {$plan->name} ({$validated['billing_cycle']})",
            'email' => $request->user()->email ?? '',
            'first_name' => $tenant->name ?? '',
        ]);

        return response()->json([
            'checkout_url' => $checkout['redirect_url'],
            'order_id' => $orderId,
            'transaction_id' => $transaction->id,
            'amount' => $amount,
            'currency' => config('pesapal.currency'),
        ]);
    }

    /**
     * Pesapal callback — user redirected here after payment.
     */
    #[OA\Get(path: "/billing/callback", tags: ["Billing"], summary: "Pesapal callback", responses: [new OA\Response(response: 200, description: "Payment status")])]
    public function callback(Request $request): JsonResponse
    {
        $orderTrackingId = $request->query('OrderTrackingId');

        if (!$orderTrackingId) {
            return response()->json(['error' => 'Missing OrderTrackingId'], 400);
        }

        $transaction = $this->pesapal->handleIpn($orderTrackingId);

        return response()->json([
            'status' => $transaction?->status ?? 'unknown',
            'order_id' => $transaction?->gateway_ref,
        ]);
    }

    /**
     * Pesapal IPN — server-to-server notification.
     */
    #[OA\Post(path: "/billing/ipn", tags: ["Billing"], summary: "Pesapal IPN notification", responses: [new OA\Response(response: 200, description: "OK")])]
    public function ipn(Request $request): JsonResponse
    {
        $orderTrackingId = $request->query('OrderTrackingId');

        if (!$orderTrackingId) {
            return response('OK', 200);
        }

        try {
            $this->pesapal->handleIpn($orderTrackingId);
        } catch (\Exception $e) {
            \Log::error('Pesapal IPN error', ['error' => $e->getMessage()]);
        }

        // Always return 200 to Pesapal
        return response('OK', 200);
    }

    /**
     * Check payment status.
     */
    #[OA\Get(path: "/billing/status/{orderId}", tags: ["Billing"], summary: "Check payment status", responses: [new OA\Response(response: 200, description: "Payment status")])]
    public function status(Request $request, string $orderId): JsonResponse
    {
        $transaction = PaymentTransaction::where('gateway_ref', $orderId)
            ->where('tenant_id', $request->attributes->get('tenant')?->id)
            ->firstOrFail();

        // If still pending, check with Pesapal
        if ($transaction->status === 'pending') {
            $this->pesapal->handleIpn($transaction->gateway_ref);
            $transaction->refresh();
        }

        return response()->json([
            'order_id' => $transaction->gateway_ref,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'created_at' => $transaction->created_at->toIso8601String(),
        ]);
    }

    /**
     * Get current subscription.
     */
    #[OA\Get(path: "/billing/subscription", tags: ["Billing"], summary: "Get current subscription", responses: [new OA\Response(response: 200, description: "Subscription details")])]
    public function currentSubscription(Request $request): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trialing', 'pending'])
            ->with('plan')
            ->latest()
            ->first();

        if (!$subscription) {
            return response()->json(['subscription' => null]);
        }

        return response()->json([
            'subscription' => [
                'id' => $subscription->id,
                'plan' => [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'price_monthly' => $subscription->plan->price_monthly,
                    'price_yearly' => $subscription->plan->price_yearly,
                ],
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'starts_at' => $subscription->starts_at?->toIso8601String(),
                'ends_at' => $subscription->ends_at?->toIso8601String(),
                'trial_ends_at' => $subscription->trial_ends_at?->toIso8601String(),
                'amount' => $subscription->amount,
                'original_amount' => $subscription->original_amount,
                'discount_percent' => $subscription->discount_percent,
            ],
        ]);
    }

    /**
     * Get billing history.
     */
    #[OA\Get(path: "/billing/history", tags: ["Billing"], summary: "Get billing history", responses: [new OA\Response(response: 200, description: "Paginated billing history")])]
    public function history(Request $request): JsonResponse
    {
        $tenantId = $request->attributes->get('tenant')?->id;

        $transactions = PaymentTransaction::where('tenant_id', $tenantId)
            ->where('type', 'subscription')
            ->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($transactions);
    }
}
