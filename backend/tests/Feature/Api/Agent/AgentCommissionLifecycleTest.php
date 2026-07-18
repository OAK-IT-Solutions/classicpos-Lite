<?php

namespace Tests\Feature\Api\Agent;

use App\Http\Middleware\TenantResolution;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\AgentReferral;
use App\Models\Landlord\AgentUser;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\PlatformSetting;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Hash;
use Tests\SaaS;

class AgentCommissionLifecycleTest extends SaaS
{

    private AgentUser $agentUser;
    private Agent $agent;
    private Tenant $tenant;
    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        config(['landlord.self_hosted' => true]);

        $this->tenant = Tenant::create([
            'name' => 'Test Business',
            'slug' => 'test-business',
            'status' => 'active',
        ]);

        $this->agentUser = AgentUser::create([
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->agent = Agent::create([
            'user_id' => $this->agentUser->id,
            'code' => 'AGT-LIFE',
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'commission_rate' => 15,
            'tier' => 'standard',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $this->plan = SubscriptionPlan::create([
            'name' => 'Professional',
            'slug' => 'professional',
            'price_monthly' => 99.00,
            'price_yearly' => 990.00,
            'max_branches' => 5,
            'max_users_per_branch' => 10,
            'max_devices_per_branch' => 5,
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->withoutMiddleware(TenantResolution::class);

        // Seed platform settings
        PlatformSetting::create(['key' => 'agent_min_payout', 'value' => '10', 'group' => 'agents', 'type' => 'integer']);
        PlatformSetting::create(['key' => 'agent_default_commission_rate', 'value' => '15', 'group' => 'agents', 'type' => 'integer']);
    }

    private function authHeader(): array
    {
        $token = $this->agentUser->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    /** Test the complete lifecycle: create referral → track click → register with code → payment → commission */
    public function test_full_commission_lifecycle(): void
    {
        // Step 1: Agent creates a referral link
        $response = $this->postJson('/api/agent/referrals', [
            'landing_url' => 'https://classicpos.com/pricing',
        ], $this->authHeader());
        $response->assertCreated();
        $referralCode = $response->json('referral_code');
        $referralId = $response->json('id');

        // Step 2: Someone clicks the referral link
        $response = $this->postJson('/api/agent/referrals/track-click', [
            'referral_code' => $referralCode,
            'ip_address' => '192.168.1.1',
        ]);
        $response->assertOk();
        $response->assertJson(['success' => true]);

        // Verify click was recorded
        $referral = AgentReferral::find($referralId);
        $this->assertNotNull($referral->clicked_at);
        $this->assertEquals('192.168.1.1', $referral->ip_address);

        // Step 3: A new user registers with the referral code
        // We simulate what AuthController::handleReferral does
        $referral->update([
            'tenant_id' => $this->tenant->id,
            'registered_at' => now(),
            'trial_started_at' => now(),
        ]);
        $referral->agent()->increment('total_referrals');

        $commission = AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 0,
            'rate' => $this->agent->commission_rate,
            'type' => 'subscription_referral',
            'status' => 'pending',
            'notes' => 'Auto-created on registration via referral code: ' . $referralCode,
        ]);

        // Verify $0 commission was created
        $this->assertDatabaseHas('agent_commissions', [
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 0,
            'status' => 'pending',
        ], 'landlord');

        // Step 4: Subscription payment succeeds — simulate CommissionService
        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $paymentTransaction = PaymentTransaction::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription',
            'amount' => 99.00,
            'currency' => 'KES',
            'gateway' => 'pesapal',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        // Call CommissionService directly
        $commissionService = app(\App\Services\CommissionService::class);
        $commissionService->processSubscriptionPayment($paymentTransaction, $subscription);

        // Step 5: Verify commission was updated with real amount
        $commission->refresh();
        $this->assertEquals(14.85, (float) $commission->amount); // 99 * 15 / 100
        $this->assertEquals(15, (float) $commission->rate);
        $this->assertEquals('subscription_referral', $commission->type);
        $this->assertEquals('pending', $commission->status);
        $this->assertNotNull($commission->subscription_id);
        $this->assertNotNull($commission->payment_transaction_id);

        // Step 6: Verify referral timestamps were updated
        $referral->refresh();
        $this->assertNotNull($referral->converted_at);
        $this->assertNotNull($referral->first_payment_at);
        $this->assertEquals(14.85, (float) $referral->commission_earned);

        // Step 7: Verify agent earnings were updated
        $this->agent->refresh();
        $this->assertEquals(14.85, (float) $this->agent->pending_earnings);
        $this->assertEquals(1, $this->agent->converted_referrals);

        // Step 8: Agent can see the commission in their list
        $response = $this->getJson('/api/agent/commissions', $this->authHeader());
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Step 9: Agent can see the referral in their list
        $response = $this->getJson('/api/agent/referrals', $this->authHeader());
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        // Step 10: Agent can view commission detail
        $response = $this->getJson("/api/agent/commissions/{$commission->id}", $this->authHeader());
        $response->assertOk();
        $response->assertJsonPath('amount', '14.85');
        $response->assertJsonPath('status', 'pending');

        // Step 11: Agent can view referral detail
        $response = $this->getJson("/api/agent/referrals/{$referralId}", $this->authHeader());
        $response->assertOk();
        $response->assertJsonPath('status', 'converted');
        $response->assertJsonPath('commission_earned', '14.85');

        // Step 12: Agent can view referral stats
        $response = $this->getJson("/api/agent/referrals/{$referralId}/stats", $this->authHeader());
        $response->assertOk();
        $response->assertJsonPath('converted', 1);
        $response->assertJsonPath('commission_earned', '14.85');
    }

    /** Test that CommissionService handles no referral gracefully */
    public function test_commission_service_skips_when_no_referral(): void
    {
        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $paymentTransaction = PaymentTransaction::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription',
            'amount' => 99.00,
            'currency' => 'KES',
            'gateway' => 'pesapal',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        $commissionService = app(\App\Services\CommissionService::class);
        $commissionService->processSubscriptionPayment($paymentTransaction, $subscription);

        // No commission should be created
        $this->assertDatabaseMissing('agent_commissions', [
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
        ], 'landlord');
    }

    /** Test that CommissionService handles inactive agent gracefully */
    public function test_commission_service_skips_when_agent_inactive(): void
    {
        $this->agent->update(['is_active' => false]);

        $referral = AgentReferral::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'referral_code' => 'AGT-LIFE-INACTIVE',
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $paymentTransaction = PaymentTransaction::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription',
            'amount' => 99.00,
            'currency' => 'KES',
            'gateway' => 'pesapal',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        $commissionService = app(\App\Services\CommissionService::class);
        $commissionService->processSubscriptionPayment($paymentTransaction, $subscription);

        // No commission should be created for inactive agent
        $this->assertDatabaseMissing('agent_commissions', [
            'agent_id' => $this->agent->id,
        ], 'landlord');
    }

    /** Test that already-converted referral is not double-processed */
    public function test_commission_service_does_not_double_convert(): void
    {
        $referral = AgentReferral::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'referral_code' => 'AGT-LIFE-CONVERTED',
            'converted_at' => now(),
            'first_payment_at' => now(),
            'commission_earned' => 14.85,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $paymentTransaction = PaymentTransaction::create([
            'tenant_id' => $this->tenant->id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription',
            'amount' => 99.00,
            'currency' => 'KES',
            'gateway' => 'pesapal',
            'status' => 'success',
            'paid_at' => now(),
        ]);

        $this->agent->update(['pending_earnings' => 0]);
        $pendingBefore = $this->agent->pending_earnings;

        $commissionService = app(\App\Services\CommissionService::class);
        $commissionService->processSubscriptionPayment($paymentTransaction, $subscription);

        // Agent earnings should not change
        $this->agent->refresh();
        $this->assertEquals($pendingBefore, (float) $this->agent->pending_earnings);
    }

    /** Test commission calculation with different rates */
    public function test_commission_calculation_with_various_rates(): void
    {
        $testCases = [
            ['rate' => 10, 'payment' => 100.00, 'expected' => 10.00],
            ['rate' => 15, 'payment' => 99.00, 'expected' => 14.85],
            ['rate' => 25, 'payment' => 200.00, 'expected' => 50.00],
            ['rate' => 5, 'payment' => 50.00, 'expected' => 2.50],
            ['rate' => 0, 'payment' => 100.00, 'expected' => 0.00],
        ];

        foreach ($testCases as $case) {
            // Reset
            $this->agent->update(['pending_earnings' => 0, 'converted_referrals' => 0]);

            $tenant = Tenant::create([
                'name' => "Tenant {$case['rate']}",
                'slug' => "tenant-{$case['rate']}",
                'status' => 'active',
            ]);

            $referral = AgentReferral::create([
                'agent_id' => $this->agent->id,
                'tenant_id' => $tenant->id,
                'referral_code' => "AGT-CALC-{$case['rate']}",
            ]);

            $this->agent->update(['commission_rate' => $case['rate']]);

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $this->plan->id,
                'status' => 'active',
                'billing_cycle' => 'monthly',
                'starts_at' => now(),
                'ends_at' => now()->addMonth(),
            ]);

            $paymentTransaction = PaymentTransaction::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'type' => 'subscription',
                'amount' => $case['payment'],
                'currency' => 'KES',
                'gateway' => 'pesapal',
                'status' => 'success',
                'paid_at' => now(),
            ]);

            $commissionService = app(\App\Services\CommissionService::class);
            $commissionService->processSubscriptionPayment($paymentTransaction, $subscription);

            $this->agent->refresh();
            $this->assertEquals(
                $case['expected'],
                (float) $this->agent->pending_earnings,
                "Commission rate {$case['rate']}% on {$case['payment']} should be {$case['expected']}"
            );
        }
    }

    /** Test that platform settings are read correctly */
    public function test_platform_settings_are_used(): void
    {
        $minPayout = PlatformSetting::get('agent_min_payout', 1);
        $this->assertEquals(10, (float) $minPayout);

        $defaultRate = PlatformSetting::get('agent_default_commission_rate', 15);
        $this->assertEquals(15, (float) $defaultRate);

        // Test default fallback when setting doesn't exist
        PlatformSetting::where('key', 'agent_min_payout')->delete();
        $minPayout = PlatformSetting::get('agent_min_payout', 1);
        $this->assertEquals(1, (float) $minPayout);
    }

    /** Test yearly subscription billing cycle */
    public function test_yearly_subscription_ends_at_one_year(): void
    {
        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'trialing',
            'billing_cycle' => 'yearly',
        ]);

        // Simulate what PesapalService::activateSubscription does
        $duration = $subscription->billing_cycle === 'yearly' ? '1 year' : '1 month';
        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->add($duration),
            'trial_ends_at' => null,
        ]);

        $subscription->refresh();
        $this->assertTrue($subscription->ends_at->isFuture());
        $this->assertGreaterThan(
            now()->addMonths(11)->timestamp,
            $subscription->ends_at->timestamp
        );
        $this->assertLessThanOrEqual(
            now()->addYear()->timestamp,
            $subscription->ends_at->timestamp
        );
    }

    /** Test monthly subscription billing cycle */
    public function test_monthly_subscription_ends_at_one_month(): void
    {
        $subscription = Subscription::create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'trialing',
            'billing_cycle' => 'monthly',
        ]);

        $duration = $subscription->billing_cycle === 'yearly' ? '1 year' : '1 month';
        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->add($duration),
            'trial_ends_at' => null,
        ]);

        $subscription->refresh();
        $this->assertTrue($subscription->ends_at->isFuture());
        $this->assertLessThanOrEqual(
            now()->addMonth()->timestamp,
            $subscription->ends_at->timestamp
        );
    }

    /** Test duplicate referral code is rejected */
    public function test_duplicate_referral_code_is_rejected(): void
    {
        AgentReferral::create([
            'agent_id' => $this->agent->id,
            'referral_code' => 'AGT-DUP-UNIQUE',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        AgentReferral::create([
            'agent_id' => $this->agent->id,
            'referral_code' => 'AGT-DUP-UNIQUE',
        ]);
    }

    /** Test agent referral filtering by status */
    public function test_referral_status_filtering(): void
    {
        AgentReferral::create([
            'agent_id' => $this->agent->id,
            'referral_code' => 'AGT-FILTER-CREATED',
        ]);

        AgentReferral::create([
            'agent_id' => $this->agent->id,
            'referral_code' => 'AGT-FILTER-CLICKED',
            'clicked_at' => now(),
        ]);

        AgentReferral::create([
            'agent_id' => $this->agent->id,
            'referral_code' => 'AGT-FILTER-REGISTERED',
            'clicked_at' => now(),
            'registered_at' => now(),
        ]);

        AgentReferral::create([
            'agent_id' => $this->agent->id,
            'referral_code' => 'AGT-FILTER-CONVERTED',
            'clicked_at' => now(),
            'registered_at' => now(),
            'converted_at' => now(),
            'commission_earned' => 50,
        ]);

        // Filter by status
        $response = $this->getJson('/api/agent/referrals?status=created', $this->authHeader());
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/agent/referrals?status=clicked', $this->authHeader());
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/agent/referrals?status=converted', $this->authHeader());
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    /** Test commission filtering by status */
    public function test_commission_status_filtering(): void
    {
        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 10,
            'rate' => 15,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 20,
            'rate' => 15,
            'type' => 'subscription_referral',
            'status' => 'paid',
        ]);

        $response = $this->getJson('/api/agent/commissions?status=pending', $this->authHeader());
        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        $response = $this->getJson('/api/agent/commissions?status=paid', $this->authHeader());
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
    }

    /** Test commission summary aggregation */
    public function test_commission_summary_aggregation(): void
    {
        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 25.00,
            'rate' => 15,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 50.00,
            'rate' => 15,
            'type' => 'subscription_referral',
            'status' => 'paid',
        ]);

        $response = $this->getJson('/api/agent/commissions/summary', $this->authHeader());
        $response->assertOk();
        $response->assertJsonStructure([
            'total_earned', 'pending', 'paid', 'this_month', 'last_month', 'by_status', 'by_type',
        ]);
    }
}
