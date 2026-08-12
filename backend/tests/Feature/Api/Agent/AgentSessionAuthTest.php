<?php

namespace Tests\Feature\Api\Agent;

use App\Http\Middleware\TenantResolution;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\AgentReferral;
use App\Models\Landlord\AgentUser;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\SaaS;

class AgentSessionAuthTest extends SaaS
{

    private AgentUser $agentUser;
    private Agent $agent;
    private Tenant $tenant;

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
            'code' => 'AGT-TEST',
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'commission_rate' => 15,
            'tier' => 'standard',
            'is_active' => true,
            'activated_at' => now(),
            'total_referrals' => 5,
            'converted_referrals' => 2,
            'total_earnings' => 150.00,
            'pending_earnings' => 50.00,
            'paid_earnings' => 100.00,
        ]);

        $this->withoutMiddleware(TenantResolution::class);
    }

    /** Test session-based auth (simulating web login + stateful API call). */
    public function test_dashboard_fetches_via_session_auth(): void
    {
        Auth::guard('web')->login($this->agentUser);
        $this->assertTrue(Auth::guard('web')->check());

        $response = $this->withHeader('Referer', 'https://localhost:9099/agent')
            ->getJson('/api/agent/dashboard');

        $response->assertOk();
        $response->assertJsonStructure([
            'overview' => ['total_referrals', 'converted_referrals', 'conversion_rate', 'tier', 'tier_label', 'commission_rate'],
            'earnings' => ['total_earnings', 'pending_earnings', 'paid_earnings'],
            'recent_commissions',
            'recent_referrals',
            'monthly_earnings',
        ]);
        $response->assertJsonPath('overview.total_referrals', 5);
        $response->assertJsonPath('overview.converted_referrals', 2);
        $response->assertJsonPath('earnings.total_earnings', '150.00');
    }

    /** Test token-based auth (simulating landing page login + API call with Bearer token). */
    public function test_dashboard_fetches_via_token_auth(): void
    {
        $token = $this->agentUser->createToken('test')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/agent/dashboard');

        $response->assertOk();
        $response->assertJsonPath('overview.total_referrals', 5);
    }

    public function test_profile_fetches_via_session_auth(): void
    {
        Auth::guard('web')->login($this->agentUser);

        $response = $this->withHeader('Referer', 'https://localhost:9099/agent')
            ->getJson('/api/agent/profile');

        $response->assertOk();
        $response->assertJsonFragment(['code' => 'AGT-TEST']);
    }

    public function test_referrals_index_fetches_via_session_auth(): void
    {
        AgentReferral::create([
            'agent_id' => $this->agent->id,
            'referral_code' => 'AGT-TEST-ABC123',
            'tenant_id' => $this->tenant->id,
            'clicked_at' => now(),
        ]);
        AgentReferral::create([
            'agent_id' => $this->agent->id,
            'referral_code' => 'AGT-TEST-DEF456',
            'tenant_id' => $this->tenant->id,
            'clicked_at' => now(),
            'registered_at' => now(),
            'converted_at' => now(),
            'commission_earned' => 50.00,
        ]);

        Auth::guard('web')->login($this->agentUser);

        $response = $this->withHeader('Referer', 'https://localhost:9099/agent')
            ->getJson('/api/agent/referrals');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_commissions_index_fetches_via_session_auth(): void
    {
        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 25.00,
            'rate' => 10.00,
            'type' => 'referral',
            'status' => 'pending',
        ]);
        AgentCommission::create([
            'agent_id' => $this->agent->id,
            'tenant_id' => $this->tenant->id,
            'amount' => 50.00,
            'rate' => 15.00,
            'type' => 'bonus',
            'status' => 'paid',
        ]);

        Auth::guard('web')->login($this->agentUser);

        $response = $this->withHeader('Referer', 'https://localhost:9099/agent')
            ->getJson('/api/agent/commissions');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
    }

    public function test_commissions_summary_fetches_via_session_auth(): void
    {
        Auth::guard('web')->login($this->agentUser);

        $response = $this->withHeader('Referer', 'https://localhost:9099/agent')
            ->getJson('/api/agent/commissions/summary');

        $response->assertOk();
        $response->assertJsonStructure([
            'total_earned', 'pending', 'paid', 'this_month', 'last_month', 'by_status', 'by_type',
        ]);
    }

    public function test_payouts_index_fetches_via_session_auth(): void
    {
        Auth::guard('web')->login($this->agentUser);

        $response = $this->withHeader('Referer', 'https://localhost:9099/agent')
            ->getJson('/api/agent/payouts');

        $response->assertOk();
    }

    public function test_unauthenticated_session_returns_401(): void
    {
        $response = $this->getJson('/api/agent/dashboard');
        $response->assertUnauthorized();
    }

    public function test_web_login_flow_via_form_submission(): void
    {
        // Simulate what AgentSessionController::login does
        $found = AgentUser::where('email', 'agent@test.com')->first();
        $this->assertNotNull($found);
        $this->assertTrue(Hash::check('password123', $found->password));

        Auth::guard('web')->login($found);

        $this->assertAuthenticated('web');

        // Verify the next request picks up the session correctly
        $response = $this->withHeader('Referer', 'https://localhost:9099/agent')
            ->getJson('/api/agent/dashboard');

        $response->assertOk();
        $response->assertJsonPath('overview.total_referrals', 5);
    }
}
