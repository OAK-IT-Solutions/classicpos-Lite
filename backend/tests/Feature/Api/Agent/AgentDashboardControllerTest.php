<?php

namespace Tests\Feature\Api\Agent;

use App\Http\Middleware\TenantResolution;
use App\Models\Landlord\Agent;
use App\Models\Landlord\Tenant;
use App\Models\User;
use Tests\SaaS;

class AgentDashboardControllerTest extends SaaS
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
            'name' => 'Test Business',
            'slug' => 'test-business',
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
            'total_referrals' => 5,
            'converted_referrals' => 2,
            'total_earnings' => 150.00,
            'pending_earnings' => 50.00,
            'paid_earnings' => 100.00,
        ]);

        // Skip TenantResolution — it switches the DB connection which breaks RefreshDatabase.
        // Agent controllers only query landlord tables, no tenant DB needed.
        $this->withoutMiddleware(TenantResolution::class);
    }

    private function authHeader(): array
    {
        $token = $this->user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    public function test_agent_can_view_dashboard(): void
    {
        $response = $this->getJson('/api/agent/dashboard', $this->authHeader());
        $response->assertOk();
        $response->assertJsonStructure([
            'overview' => ['total_referrals', 'converted_referrals', 'conversion_rate'],
            'earnings' => ['total_earnings', 'pending_earnings', 'paid_earnings'],
        ]);
    }

    public function test_agent_can_view_profile(): void
    {
        $response = $this->getJson('/api/agent/profile', $this->authHeader());
        $response->assertOk();
        $response->assertJsonFragment(['code' => 'AGENT001']);
    }

    public function test_non_agent_gets_403(): void
    {
        $user2 = User::factory()->create();
        $token = $user2->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/agent/dashboard', [
            'Authorization' => "Bearer $token",
        ]);
        $response->assertForbidden();
    }
}
