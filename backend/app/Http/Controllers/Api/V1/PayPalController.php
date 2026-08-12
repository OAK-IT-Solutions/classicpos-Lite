<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\PlanDiscount;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use App\Services\PayPalService;
use App\Services\SubscriptionActivationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'PayPal', description: 'PayPal payment gateway integration for subscriptions')]
class PayPalController extends Controller
{
    public function __construct(
        private PayPalService $paypal,
        private SubscriptionActivationService $subscriptionActivator,
    ) {}

    #[OA\Post(
        path: '/api/v1/paypal/create-order',
        tags: ['PayPal'],
        summary: 'Create a PayPal order for subscription payment',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'plan_id', type: 'string'),
            new OA\Property(property: 'billing_cycle', type: 'string', enum: ['monthly', 'yearly']),
            new OA\Property(property: 'discount_code', type: 'string'),
        ], required: ['plan_id', 'billing_cycle'])),
        responses: [
            new OA\Response(response: 200, description: 'PayPal order created with approval URL'),
            new OA\Response(response: 422, description: 'Validation error'),
        ]
    )]
    public function createOrder(Request $request): JsonResponse
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

        $orderId = 'PAYPAL-' . strtoupper(Str::random(12));

        $transaction = PaymentTransaction::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => null,
            'type' => 'subscription',
            'amount' => $amount,
            'currency' => config('paypal.currency'),
            'gateway' => 'paypal',
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

        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', ['pending', 'trialing'])
            ->first();

        if (!$subscription) {
            $period = $validated['billing_cycle'] === 'yearly' ? 'P1Y' : 'P1M';

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'status' => 'trialing',
                'starts_at' => now(),
                'ends_at' => now()->add($period),
                'amount' => $amount,
                'original_amount' => $originalAmount,
                'discount_id' => $discountId,
                'discount_percent' => $discountPercent,
                'paypal_order_id' => $orderId,
            ]);
        } else {
            $subscription->update([
                'plan_id' => $plan->id,
                'billing_cycle' => $validated['billing_cycle'],
                'amount' => $amount,
                'original_amount' => $originalAmount,
                'discount_id' => $discountId,
                'discount_percent' => $discountPercent,
                'paypal_order_id' => $orderId,
            ]);
        }

        $transaction->update(['subscription_id' => $subscription->id]);

        $returnUrl = config('app.url') . '/settings?tab=billing&paypal_success=' . $orderId;
        $cancelUrl = config('app.url') . '/settings?tab=billing&paypal_cancelled=' . $orderId;

        $paypalOrder = $this->paypal->createOrder(
            $amount,
            "ClassicPOS {$plan->name} ({$validated['billing_cycle']})",
            $returnUrl,
            $cancelUrl,
        );

        $transaction->update([
            'gateway_ref' => $paypalOrder['order_id'],
            'metadata' => array_merge($transaction->metadata ?? [], [
                'paypal_order_id' => $paypalOrder['order_id'],
                'merchant_reference' => $orderId,
            ]),
        ]);

        $subscription->update(['paypal_order_id' => $paypalOrder['order_id']]);

        return response()->json([
            'order_id' => $paypalOrder['order_id'],
            'approval_url' => $paypalOrder['approval_url'],
            'merchant_reference' => $orderId,
            'amount' => $amount,
            'currency' => config('paypal.currency'),
        ]);
    }

    #[OA\Post(
        path: '/api/v1/paypal/capture/{orderId}',
        tags: ['PayPal'],
        summary: 'Capture a completed PayPal order',
        parameters: [new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Payment captured successfully'),
            new OA\Response(response: 422, description: 'Capture failed'),
        ]
    )]
    public function captureOrder(Request $request, string $orderId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        try {
            $result = $this->paypal->captureOrder($orderId);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => 'Capture failed',
                'message' => $e->getMessage(),
                'order_id' => $orderId,
            ], 422);
        }

        $transaction = PaymentTransaction::where('gateway_ref', $orderId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $mappedStatus = config("paypal.status_map.{$result['status']}", 'pending');
        $isCompleted = $result['capture_status'] === 'COMPLETED';

        if ($isCompleted && $transaction->status !== 'success') {
            $transaction->update([
                'status' => 'success',
                'gateway_ref' => $result['capture_id'] ?? $orderId,
                'order_tracking_id' => $result['capture_id'] ?? null,
                'paid_at' => now(),
                'gateway_response' => $result['full_response'],
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'paypal_order_id' => $result['order_id'],
                    'capture_id' => $result['capture_id'],
                    'captured_at' => $result['create_time'],
                ]),
            ]);

            $this->subscriptionActivator->activateFromTransaction($transaction);

            return response()->json([
                'status' => 'success',
                'capture_id' => $result['capture_id'],
                'amount' => $result['amount'],
                'currency' => $result['currency'],
            ]);
        }

        $transaction->update([
            'status' => $mappedStatus === 'success' ? 'success' : $mappedStatus,
            'gateway_response' => $result['full_response'] ?? null,
        ]);

        return response()->json([
            'status' => $mappedStatus,
            'order_id' => $result['order_id'],
        ]);
    }

    #[OA\Post(
        path: '/api/v1/paypal/webhook',
        tags: ['PayPal'],
        summary: 'PayPal webhook receiver',
        description: 'Receives and verifies PayPal payment events (capture, refund, denial).',
        responses: [
            new OA\Response(response: 200, description: 'Webhook processed'),
            new OA\Response(response: 403, description: 'Verification failed'),
        ]
    )]
    public function webhook(Request $request): JsonResponse
    {
        $payload = $request->all();
        $headers = [
            'paypal-auth-algo' => $request->header('PAYPAL-AUTH-ALGO', ''),
            'paypal-cert-url' => $request->header('PAYPAL-CERT-URL', ''),
            'paypal-transmission-id' => $request->header('PAYPAL-TRANSMISSION-ID', ''),
            'paypal-transmission-sig' => $request->header('PAYPAL-TRANSMISSION-SIG', ''),
            'paypal-transmission-time' => $request->header('PAYPAL-TRANSMISSION-TIME', ''),
        ];

        $verified = $this->paypal->verifyWebhook($payload, $headers);
        if (!$verified) {
            Log::warning('PayPal webhook verification failed', ['event_type' => $payload['event_type'] ?? null]);
            return response()->json(['error' => 'Verification failed'], 403);
        }

        $eventType = $payload['event_type'] ?? '';
        $resource = $payload['resource'] ?? [];

        switch ($eventType) {
            case 'PAYMENT.CAPTURE.COMPLETED':
                $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
                $captureId = $resource['id'] ?? null;

                if (!$orderId) {
                    return response()->json(['error' => 'Missing order_id'], 400);
                }

                $transaction = PaymentTransaction::where('gateway_ref', $orderId)->first();

                if ($transaction && $transaction->status !== 'success') {
                    $transaction->update([
                        'status' => 'success',
                        'order_tracking_id' => $captureId,
                        'paid_at' => now(),
                        'gateway_response' => $resource,
                    ]);

                    $this->subscriptionActivator->activateFromTransaction($transaction);
                }
                break;

            case 'PAYMENT.CAPTURE.DENIED':
            case 'PAYMENT.CAPTURE.DECLINED':
                $orderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;
                if ($orderId) {
                    PaymentTransaction::where('gateway_ref', $orderId)
                        ->where('status', 'pending')
                        ->update(['status' => 'failed']);
                }
                break;

            case 'PAYMENT.CAPTURE.REFUNDED':
                $captureId = $resource['id'] ?? null;
                if ($captureId) {
                    PaymentTransaction::where('order_tracking_id', $captureId)
                        ->where('status', 'success')
                        ->update([
                            'status' => 'refunded',
                            'refunded_at' => now(),
                        ]);
                }
                break;
        }

        return response()->json(['status' => 'ok']);
    }

    #[OA\Get(
        path: '/api/v1/paypal/status/{orderId}',
        tags: ['PayPal'],
        summary: 'Get PayPal order status',
        parameters: [new OA\Parameter(name: 'orderId', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: 'Order status'),
            new OA\Response(response: 404, description: 'Transaction not found'),
        ]
    )]
    public function status(Request $request, string $orderId): JsonResponse
    {
        $tenant = $request->attributes->get('tenant');

        $transaction = PaymentTransaction::where('gateway_ref', $orderId)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        return response()->json([
            'order_id' => $orderId,
            'status' => $transaction->status,
            'amount' => $transaction->amount,
            'created_at' => $transaction->created_at->toIso8601String(),
        ]);
    }
}
