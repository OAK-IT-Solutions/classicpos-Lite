<?php

namespace Tests\Feature\Api\Agent;

use App\Http\Middleware\TenantResolution;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\Tenant;
use App\Models\User;
use Tests\SaaS;

class AgentCommissionControllerTest extends SaaS
{

    private User $user;
    private Agent $agent;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        config(['landlord.self_hosted' => false]);
        $this->app['config']->set('landlord.self_hosted', false);

        $this->tenant = Tenant::create([
            'name' => 'Test Co',
            'slug' => 'test-co',
            'status' => 'active',
        ]);

        $this->user = User::factory()->create();
        $this->agent = Agent::create([
            'user_id' => $this->user->id,
            'code' => 'AGENT001',
            'name' => 'Test Agent',
            'email' => $this->user->email,
            'commission_rate' => 15,
            'tier' => 'standard',
            'is_active' => true,
        ]);

        $this->withoutMiddleware(TenantResolution::class);
    }

    private function authHeader(): array
    {
        $token = $this->user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    public function test_agent_can_list_commissions(): void
    {
        $commission = AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 100.00,
            'rate' => 15.00,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agent/commissions');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $commission->id);
    }

    public function test_agent_can_view_summary(): void
    {
        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 100.00,
            'rate' => 15.00,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);
        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 200.00,
            'rate' => 15.00,
            'type' => 'subscription_referral',
            'status' => 'cleared',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agent/commissions/summary');

        $response->assertOk();
        $response->assertJsonStructure(['total_earned', 'pending', 'paid', 'this_month', 'last_month', 'by_status', 'by_type']);
    }

    public function test_agent_cannot_see_other_agents_commissions(): void
    {
        $otherAgent = Agent::create([
            'user_id' => $this->user->id,
            'code' => 'AGENT002',
            'name' => 'Other Agent',
            'email' => 'other@example.com',
            'commission_rate' => 10,
            'tier' => 'standard',
            'is_active' => true,
        ]);

        AgentCommission::create([
            'agent_id' => $otherAgent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 500.00,
            'rate' => 10.00,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $response = $this->withHeaders($this->authHeader())
            ->getJson('/api/agent/commissions');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }
}
