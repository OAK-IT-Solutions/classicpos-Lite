<?php

namespace Tests\Feature\Api\Agent;

use App\Http\Middleware\TenantResolution;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\AgentReferral;
use App\Models\Landlord\AgentUser;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Hash;
use Tests\SaaS;

class AgentEdgeCaseTest extends SaaS
{

    private AgentUser $agentUser;
    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        config(['landlord.self_hosted' => true]);

        $this->agentUser = AgentUser::create([
            'name' => 'Edge Agent',
            'email' => 'edge@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->agent = Agent::create([
            'user_id' => $this->agentUser->id,
            'code' => 'AGT-EDGE',
            'name' => 'Edge Agent',
            'email' => 'edge@test.com',
            'commission_rate' => 15,
            'tier' => 'standard',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $this->withoutMiddleware(TenantResolution::class);
    }

    /** Test agent data isolation - agent cannot see other agents' data */
    public function test_agent_cannot_see_other_agents_referrals(): void
    {
        $otherUser = AgentUser::create([
            'name' => 'Other Agent',
            'email' => 'other@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $otherAgent = Agent::create([
            'user_id' => $otherUser->id,
            'code' => 'AGT-OTHER',
            'name' => 'Other Agent',
            'email' => 'other@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
        ]);

        AgentReferral::create([
            'agent_id' => $otherAgent->id,
            'tenant_id' => $otherTenant->id,
            'referral_code' => 'AGT-OTHER-REF1',
        ]);

        $token = $this->agentUser->createToken('test')->plainTextToken;
        $response = $this->getJson('/api/agent/referrals', ['Authorization' => "Bearer $token"]);
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_agent_cannot_see_other_agents_commissions(): void
    {
        $otherUser = AgentUser::create([
            'name' => 'Other Agent',
            'email' => 'other2@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $otherAgent = Agent::create([
            'user_id' => $otherUser->id,
            'code' => 'AGT-OTHER2',
            'name' => 'Other Agent 2',
            'email' => 'other2@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        AgentCommission::create([
            'agent_id' => $otherAgent->id,
            'amount' => 100,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $token = $this->agentUser->createToken('test')->plainTextToken;
        $response = $this->getJson('/api/agent/commissions', ['Authorization' => "Bearer $token"]);
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    /** Test deactivation prevents commission processing */
    public function test_deactivated_agent_does_not_get_commission(): void
    {
        $tenant = Tenant::create([
            'name' => 'Deactivated Tenant',
            'slug' => 'deactivated-tenant',
            'status' => 'active',
        ]);

        $referral = AgentReferral::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $tenant->id,
            'referral_code' => 'AGT-DEACT-REF',
        ]);

        // Deactivate the agent
        $this->agent->update(['is_active' => false]);

        $plan = SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price_monthly' => 99,
            'price_yearly' => 990,
            'max_branches' => 1,
            'max_users_per_branch' => 3,
            'max_devices_per_branch' => 2,
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $paymentTransaction = PaymentTransaction::create([
            'tenant_id' => $tenant->id,
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
        ]);
    }

    /** Test multiple referrals for the same agent */
    public function test_agent_can_have_multiple_referrals(): void
    {
        $token = $this->agentUser->createToken('test')->plainTextToken;

        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/agent/referrals', [], ['Authorization' => "Bearer $token"]);
            $response->assertCreated();
        }

        $this->assertDatabaseCount('agent_referrals', 5, 'landlord');
    }

    /** Test referral code uniqueness across agents */
    public function test_referral_codes_are_unique_across_agents(): void
    {
        $otherUser = AgentUser::create([
            'name' => 'Other Agent',
            'email' => 'other3@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $otherAgent = Agent::create([
            'user_id' => $otherUser->id,
            'code' => 'AGT-OTHER3',
            'name' => 'Other Agent 3',
            'email' => 'other3@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        $token1 = $this->agentUser->createToken('test')->plainTextToken;
        $token2 = $otherUser->createToken('test')->plainTextToken;

        $response1 = $this->postJson('/api/agent/referrals', [], ['Authorization' => "Bearer $token1"]);
        $response2 = $this->postJson('/api/agent/referrals', [], ['Authorization' => "Bearer $token2"]);

        $code1 = $response1->json('referral_code');
        $code2 = $response2->json('referral_code');

        $this->assertNotEquals($code1, $code2);
    }

    /** Test referral show returns 404 for non-existent referral */
    public function test_referral_show_returns_404_for_nonexistent(): void
    {
        $token = $this->agentUser->createToken('test')->plainTextToken;
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this->getJson("/api/agent/referrals/{$fakeId}", ['Authorization' => "Bearer $token"]);
        $response->assertStatus(404);
    }

    /** Test commission show returns 404 for non-existent commission */
    public function test_commission_show_returns_404_for_nonexistent(): void
    {
        $token = $this->agentUser->createToken('test')->plainTextToken;
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this->getJson("/api/agent/commissions/{$fakeId}", ['Authorization' => "Bearer $token"]);
        $response->assertStatus(404);
    }

    /** Test track click with invalid code returns 404 */
    public function test_track_click_invalid_code_returns_404(): void
    {
        $response = $this->postJson('/api/agent/referrals/track-click', [
            'referral_code' => 'INVALID-CODE',
        ]);
        $response->assertStatus(404);
    }

    /** Test track click is idempotent (first-click-wins) */
    public function test_track_click_is_idempotent(): void
    {
        $referral = AgentReferral::create([
            'agent_id' => $this->agent->id,
            'referral_code' => 'AGT-CLICK-IDEMP',
        ]);

        $this->postJson('/api/agent/referrals/track-click', [
            'referral_code' => 'AGT-CLICK-IDEMP',
        ])->assertOk();

        $firstClick = $referral->fresh()->clicked_at;

        // Click again - should not change clicked_at
        $this->postJson('/api/agent/referrals/track-click', [
            'referral_code' => 'AGT-CLICK-IDEMP',
        ])->assertOk();

        $referral->refresh();
        $this->assertEquals($firstClick->timestamp, $referral->clicked_at->timestamp);
    }

    /** Test payout show returns 404 for non-existent payout */
    public function test_payout_show_returns_404_for_nonexistent(): void
    {
        $token = $this->agentUser->createToken('test')->plainTextToken;
        $fakeId = '00000000-0000-0000-0000-000000000000';
        $response = $this->getJson("/api/agent/payouts/{$fakeId}", ['Authorization' => "Bearer $token"]);
        $response->assertStatus(404);
    }

    /** Test agent cannot see other agents' payouts */
    public function test_agent_cannot_see_other_agents_payouts(): void
    {
        $otherUser = AgentUser::create([
            'name' => 'Other Agent',
            'email' => 'other4@test.com',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $otherAgent = Agent::create([
            'user_id' => $otherUser->id,
            'code' => 'AGT-OTHER4',
            'name' => 'Other Agent 4',
            'email' => 'other4@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        PaymentTransaction::create([
            'agent_id' => $otherAgent->id,
            'type' => 'payout',
            'amount' => 100,
            'currency' => 'KES',
            'gateway' => 'mobile_money',
            'status' => 'pending',
        ]);

        $token = $this->agentUser->createToken('test')->plainTextToken;
        $response = $this->getJson('/api/agent/payouts', ['Authorization' => "Bearer $token"]);
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    /** Test 0% commission rate results in $0 commission */
    public function test_zero_commission_rate(): void
    {
        $this->agent->update(['commission_rate' => 0]);

        $tenant = Tenant::create([
            'name' => 'Zero Rate Tenant',
            'slug' => 'zero-rate-tenant',
            'status' => 'active',
        ]);

        $referral = AgentReferral::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $tenant->id,
            'referral_code' => 'AGT-ZERO-REF',
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price_monthly' => 99,
            'price_yearly' => 990,
            'max_branches' => 1,
            'max_users_per_branch' => 3,
            'max_devices_per_branch' => 2,
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $paymentTransaction = PaymentTransaction::create([
            'tenant_id' => $tenant->id,
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

        $this->assertDatabaseHas('agent_commissions', [
            'agent_id' => $this->agent->id,
            'amount' => 0,
        ]);
    }

    /** Test 100% commission rate */
    public function test_hundred_percent_commission_rate(): void
    {
        $this->agent->update(['commission_rate' => 100]);

        $tenant = Tenant::create([
            'name' => 'Hundred Rate Tenant',
            'slug' => 'hundred-rate-tenant',
            'status' => 'active',
        ]);

        $referral = AgentReferral::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $tenant->id,
            'referral_code' => 'AGT-100-REF',
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Test Plan',
            'slug' => 'test-plan',
            'price_monthly' => 99,
            'price_yearly' => 990,
            'max_branches' => 1,
            'max_users_per_branch' => 3,
            'max_devices_per_branch' => 2,
            'is_active' => true,
        ]);

        $subscription = Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'billing_cycle' => 'monthly',
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
        ]);

        $paymentTransaction = PaymentTransaction::create([
            'tenant_id' => $tenant->id,
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

        $this->assertDatabaseHas('agent_commissions', [
            'agent_id' => $this->agent->id,
            'amount' => 99.00,
        ]);

        $this->agent->refresh();
        $this->assertEquals(99.00, (float) $this->agent->pending_earnings);
    }
}
