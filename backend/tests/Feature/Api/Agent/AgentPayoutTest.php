<?php

namespace Tests\Feature\Api\Agent;

use App\Http\Middleware\TenantResolution;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\AgentUser;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\PlatformSetting;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Hash;
use Tests\SaaS;

class AgentPayoutTest extends SaaS
{

    private AgentUser $agentUser;
    private Agent $agent;

    protected function setUp(): void
    {
        parent::setUp();
        config(['landlord.self_hosted' => true]);

        $this->agentUser = AgentUser::create([
            'name' => 'Payout Agent',
            'email' => 'payout@test.com',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $this->agent = Agent::create([
            'user_id' => $this->agentUser->id,
            'code' => 'AGT-PAY',
            'name' => 'Payout Agent',
            'email' => 'payout@test.com',
            'commission_rate' => 15,
            'tier' => 'standard',
            'is_active' => true,
            'activated_at' => now(),
            'pending_earnings' => 100.00,
            'paid_earnings' => 50.00,
            'total_earnings' => 150.00,
        ]);

        PlatformSetting::create(['key' => 'agent_min_payout', 'value' => '10', 'group' => 'agents', 'type' => 'integer']);
        PlatformSetting::create(['key' => 'default_currency', 'value' => 'KES', 'group' => 'general']);

        $this->withoutMiddleware(TenantResolution::class);
    }

    private function authHeader(): array
    {
        $token = $this->agentUser->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    public function test_agent_can_request_payout(): void
    {
        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'amount' => 50,
            'rate' => 15,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/agent/payouts/request', [
            'amount' => 50,
            'method' => 'mobile_money',
            'account_details' => [
                'phone' => '+254712345678',
                'network' => 'Safaricom',
            ],
        ], $this->authHeader());

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'amount', 'gateway', 'gateway_ref', 'status']);

        // Verify payout transaction was created
        $this->assertDatabaseHas('payment_transactions', [
            'agent_id' => $this->agent->id,
            'type' => 'payout',
            'amount' => 50,
            'status' => 'pending',
        ], 'landlord');

        // Verify agent earnings were updated
        $this->agent->refresh();
        $this->assertEquals(50, (float) $this->agent->pending_earnings); // 100 - 50
        $this->assertEquals(100, (float) $this->agent->paid_earnings); // 50 + 50
    }

    public function test_agent_cannot_request_payout_below_minimum(): void
    {
        $response = $this->postJson('/api/agent/payouts/request', [
            'amount' => 5, // Below minimum of 10
            'method' => 'mobile_money',
            'account_details' => [
                'phone' => '+254712345678',
                'network' => 'Safaricom',
            ],
        ], $this->authHeader());

        $response->assertStatus(422);
    }

    public function test_agent_cannot_request_payout_exceeding_balance(): void
    {
        $response = $this->postJson('/api/agent/payouts/request', [
            'amount' => 200, // More than pending_earnings of 100
            'method' => 'mobile_money',
            'account_details' => [
                'phone' => '+254712345678',
                'network' => 'Safaricom',
            ],
        ], $this->authHeader());

        $response->assertStatus(422);
    }

    public function test_agent_can_list_payouts(): void
    {
        PaymentTransaction::create([
            'agent_id' => $this->agent->id,
            'type' => 'payout',
            'amount' => 50,
            'currency' => 'KES',
            'gateway' => 'mobile_money',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/agent/payouts', $this->authHeader());
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_agent_can_show_payout(): void
    {
        $payout = PaymentTransaction::create([
            'agent_id' => $this->agent->id,
            'type' => 'payout',
            'amount' => 50,
            'currency' => 'KES',
            'gateway' => 'mobile_money',
            'gateway_ref' => 'PAY-TEST123',
            'status' => 'pending',
        ]);

        $response = $this->getJson("/api/agent/payouts/{$payout->id}", $this->authHeader());
        $response->assertOk();
        $response->assertJsonPath('amount', '50.00');
        $response->assertJsonPath('gateway_ref', 'PAY-TEST123');
    }

    public function test_payout_marks_commissions_as_paid(): void
    {
        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'amount' => 30,
            'rate' => 15,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'amount' => 20,
            'rate' => 15,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/agent/payouts/request', [
            'amount' => 50,
            'method' => 'bank',
            'account_details' => [
                'bank_name' => 'KCB',
                'account_number' => '1234567890',
            ],
        ], $this->authHeader());

        $response->assertCreated();

        // Both commissions should be marked as paid
        $this->assertDatabaseHas('agent_commissions', [
            'agent_id' => $this->agent->id,
            'amount' => 30,
            'status' => 'paid',
        ], 'landlord');
        $this->assertDatabaseHas('agent_commissions', [
            'agent_id' => $this->agent->id,
            'amount' => 20,
            'status' => 'paid',
        ], 'landlord');
    }

    public function test_payout_with_pesapal_method(): void
    {
        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'amount' => 100,
            'rate' => 15,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/agent/payouts/request', [
            'amount' => 100,
            'method' => 'pesapal',
            'account_details' => [
                'email' => 'agent@test.com',
            ],
        ], $this->authHeader());

        $response->assertCreated();
        $response->assertJsonPath('gateway', 'pesapal');
    }

    public function test_platform_settings_control_min_payout(): void
    {
        // Update min payout to 50
        PlatformSetting::where('key', 'agent_min_payout')->update(['value' => '50']);

        $response = $this->postJson('/api/agent/payouts/request', [
            'amount' => 30, // Below new minimum of 50
            'method' => 'mobile_money',
            'account_details' => [
                'phone' => '+254712345678',
                'network' => 'Safaricom',
            ],
        ], $this->authHeader());

        $response->assertStatus(422);
    }
}
