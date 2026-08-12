<?php

namespace Tests\Feature\Api\Agent;

use App\Http\Middleware\TenantResolution;
use App\Models\Landlord\Agent;
use App\Models\Landlord\Tenant;
use App\Models\User;
use Tests\SaaS;

class AgentReferralControllerTest extends SaaS
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
        ]);

        $this->withoutMiddleware(TenantResolution::class);
    }

    private function authHeader(): array
    {
        $token = $this->user->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    public function test_agent_can_list_referrals(): void
    {
        $response = $this->getJson('/api/agent/referrals', $this->authHeader());
        $response->assertOk();
    }

    public function test_agent_can_create_referral(): void
    {
        $response = $this->postJson('/api/agent/referrals', [
            'landing_url' => 'https://classicpos.com/pricing',
        ], $this->authHeader());

        $response->assertCreated();
        $response->assertJsonStructure(['id', 'referral_code']);
    }

    public function test_referral_code_is_unique(): void
    {
        $response1 = $this->postJson('/api/agent/referrals', [], $this->authHeader());
        $response2 = $this->postJson('/api/agent/referrals', [], $this->authHeader());

        $code1 = $response1->json('referral_code');
        $code2 = $response2->json('referral_code');

        $this->assertNotEquals($code1, $code2);
    }
}
