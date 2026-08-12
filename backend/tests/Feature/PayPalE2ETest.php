<?php

namespace Tests\Feature;

use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\Tenant;
use App\Services\PayPalService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\SaaS;

class PayPalE2ETest extends SaaS
{

    private Tenant $tenant;
    private SubscriptionPlan $plan;
    private PayPalService $paypal;
    private string $paypalToken;

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('landlord.self_hosted', false);
        $app['config']->set('landlord.resolution', 'header');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->paypal = app(PayPalService::class);
        $this->plan = SubscriptionPlan::firstOrCreate(
            ['slug' => 'e2e-professional'],
            [
                'name' => 'E2E Professional',
                'price_monthly' => 29.00,
                'price_yearly' => 290.00,
                'description' => 'E2E test plan',
                'is_active' => true,
            ]
        );

        $this->tenant = Tenant::create([
            'name' => 'E2E Test Tenant',
            'slug' => 'e2e-test-' . uniqid(),
            'domain' => 'e2e-test.localhost',
            'db_host' => 'db',
            'db_port' => 5444,
            'db_name' => 'classicpos',
            'db_username' => 'classicpos',
            'db_password' => 'classicpos',
            'status' => 'active',
        ]);

        try {
            $tokenResponse = Http::withBasicAuth(
                config('paypal.client_id'),
                config('paypal.client_secret'),
            )->asForm()->post(config('paypal.api_url') . '/v1/oauth2/token', [
                'grant_type' => 'client_credentials',
            ]);

            if ($tokenResponse->failed()) {
                $this->markTestSkipped('PayPal sandbox unavailable: ' . $tokenResponse->body());
            }

            $this->paypalToken = $tokenResponse->json('access_token');
        } catch (ConnectionException $e) {
            $this->markTestSkipped('Cannot reach PayPal sandbox: ' . $e->getMessage());
        }
    }

    public function test_full_paypal_subscription_flow(): void
    {
        $this->markTestSkipped('Requires manual buyer approval via PayPal checkout popup. Run locally and approve the order at the approval_url to complete capture.');

        $amount = $this->plan->price_monthly;
        $returnUrl = 'https://localhost:9099/settings?tab=billing';
        $cancelUrl = 'https://localhost:9099/settings?tab=billing';

        $order = $this->paypal->createOrder($amount, "ClassicPOS {$this->plan->name} (monthly)", $returnUrl, $cancelUrl);

        $this->assertArrayHasKey('order_id', $order);
        $this->assertArrayHasKey('approval_url', $order);
        $this->assertNotNull($order['approval_url']);
        echo "\n✅ Step 1: Created PayPal order: {$order['order_id']}";

        $orderDetails = $this->paypal->getOrder($order['order_id']);
        $this->assertEquals('CREATED', $orderDetails['status']);
        echo "\n✅ Step 2: Order status is CREATED";

        $captureResult = $this->paypal->captureOrder($order['order_id']);
        $this->assertEquals('COMPLETED', $captureResult['capture_status']);
        echo "\n✅ Step 3: Order captured successfully: {$captureResult['capture_id']}";
        echo "\n✅ Full PayPal REST flow verified!";
    }

    public function test_backend_create_order_endpoint(): void
    {
        $user = \App\Models\User::factory()->create();

        $response = $this->withHeader('X-Tenant-ID', $this->tenant->id)
            ->actingAs($user)
            ->postJson('/api/v1/billing/paypal/create-order', [
                'plan_id' => $this->plan->id,
                'billing_cycle' => 'monthly',
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['order_id', 'approval_url', 'merchant_reference', 'amount']);

        echo "\n✅ Backend create-order: {$response->json('order_id')}";

        $transaction = PaymentTransaction::where('gateway', 'paypal')
            ->where('gateway_ref', $response->json('order_id'))
            ->first();

        $this->assertNotNull($transaction);
        $this->assertEquals('pending', $transaction->status);
        echo "\n✅ Payment transaction created with status: pending";
    }

    public function test_backend_capture_and_activate_subscription(): void
    {
        $this->markTestSkipped('Requires manual buyer approval via PayPal checkout popup. Run locally and approve the order at the approval_url to complete capture.');

        $amount = $this->plan->price_monthly;

        $order = $this->paypal->createOrder(
            $amount,
            "ClassicPOS {$this->plan->name} (monthly)",
            'https://localhost/callback',
            'https://localhost/cancel',
        );

        $captureResult = $this->paypal->captureOrder($order['order_id']);
        $this->assertEquals('COMPLETED', $captureResult['capture_status']);

        $transaction = PaymentTransaction::create([
            'tenant_id' => $this->tenant->id,
            'type' => 'subscription',
            'amount' => $amount,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'gateway_ref' => $captureResult['capture_id'],
            'order_tracking_id' => $captureResult['capture_id'],
            'status' => 'success',
            'paid_at' => now(),
            'metadata' => [
                'plan_id' => $this->plan->id,
                'billing_cycle' => 'monthly',
                'paypal_order_id' => $order['order_id'],
                'capture_id' => $captureResult['capture_id'],
            ],
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'trialing',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => $amount,
            'original_amount' => $this->plan->price_monthly,
            'paypal_order_id' => $order['order_id'],
        ]);

        $transaction->update(['subscription_id' => $subscription->id]);

        $activator = app(\App\Services\SubscriptionActivationService::class);
        $activator->activateFromTransaction($transaction);

        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertTrue(now()->diffInDays($subscription->ends_at) > 25);

        $this->tenant->refresh();
        $this->assertEquals('active', $this->tenant->status);

        echo "\n✅ Subscription activated: {$subscription->id}";
        echo "\n✅ Tenant status: {$this->tenant->status}";
        echo "\n✅ Subscription ends at: {$subscription->ends_at}";
    }

    public function test_backend_webhook_payment_capture_completed(): void
    {
        $this->markTestSkipped('Requires manual buyer approval via PayPal checkout popup. Run locally and approve the order at the approval_url to complete capture.');

        $amount = $this->plan->price_monthly;

        $order = $this->paypal->createOrder(
            $amount,
            "ClassicPOS {$this->plan->name} (monthly)",
            'https://localhost/callback',
            'https://localhost/cancel',
        );

        $captureResult = $this->paypal->captureOrder($order['order_id']);

        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'billing_cycle' => 'monthly',
            'status' => 'trialing',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'amount' => $amount,
            'original_amount' => $this->plan->price_monthly,
            'paypal_order_id' => $order['order_id'],
        ]);

        PaymentTransaction::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription',
            'amount' => $amount,
            'currency' => 'USD',
            'gateway' => 'paypal',
            'gateway_ref' => $order['order_id'],
            'status' => 'pending',
            'metadata' => [
                'plan_id' => $this->plan->id,
                'billing_cycle' => 'monthly',
                'paypal_order_id' => $order['order_id'],
                'capture_id' => $captureResult['capture_id'],
            ],
        ]);

        $webhookPayload = [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => $captureResult['capture_id'],
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => $order['order_id'],
                    ],
                ],
                'status' => 'COMPLETED',
                'amount' => [
                    'value' => $amount,
                    'currency_code' => 'USD',
                ],
                'create_time' => now()->toIso8601String(),
            ],
        ];

        $response = $this->postJson('/api/v1/billing/paypal/webhook', $webhookPayload);

        $response->assertStatus(403);
        echo "\n✅ Webhook correctly returns 403 (no webhook ID configured)";
        echo "\nℹ️  Set PAYPAL_WEBHOOK_ID in .env for full webhook verification";
    }
}
