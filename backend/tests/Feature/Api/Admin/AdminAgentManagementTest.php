<?php

namespace Tests\Feature\Api\Admin;

use App\Http\Middleware\TenantResolution;
use App\Models\Landlord\AdminUser;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\AgentReferral;
use App\Models\Landlord\AuditLog;
use App\Models\Landlord\PlatformSetting;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Hash;
use Tests\SaaS;

class AdminAgentManagementTest extends SaaS
{

    private AdminUser $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['landlord.self_hosted' => true]);

        $this->admin = AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@test.com',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $this->withoutMiddleware(TenantResolution::class);
    }

    private function authHeader(): array
    {
        $token = $this->admin->createToken('test', ['*'])->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    // --- Agent CRUD ---

    public function test_admin_can_list_agents(): void
    {
        Agent::create([
            'code' => 'AGT-LIST1',
            'name' => 'Agent One',
            'email' => 'agent1@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/admin/agents', $this->authHeader());
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_admin_can_create_agent(): void
    {
        PlatformSetting::create(['key' => 'agent_default_commission_rate', 'value' => '20', 'group' => 'agents']);

        $response = $this->postJson('/api/v1/admin/agents', [
            'name' => 'New Agent',
            'email' => 'newagent@test.com',
            'password' => 'Secret123!',
        ], $this->authHeader());

        $response->assertCreated();
        $response->assertJsonFragment(['name' => 'New Agent']);
        $response->assertJsonStructure(['agent']);

        $this->assertDatabaseHas('agents', [
            'email' => 'newagent@test.com',
            'commission_rate' => 20, // from PlatformSetting
        ], 'landlord');

        // Verify AgentUser was created
        $this->assertDatabaseHas('agent_users', [
            'email' => 'newagent@test.com',
        ], 'landlord');
    }

    public function test_admin_create_agent_with_custom_rate(): void
    {
        $response = $this->postJson('/api/v1/admin/agents', [
            'name' => 'Custom Rate Agent',
            'email' => 'custom@test.com',
            'password' => 'Secret123!',
            'commission_rate' => 25,
        ], $this->authHeader());

        $response->assertCreated();
        $this->assertDatabaseHas('agents', [
            'email' => 'custom@test.com',
            'commission_rate' => 25,
        ], 'landlord');
    }

    public function test_admin_cannot_create_agent_with_duplicate_email(): void
    {
        Agent::create([
            'code' => 'AGT-DUP',
            'name' => 'Existing Agent',
            'email' => 'dup@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/agents', [
            'name' => 'Duplicate Agent',
            'email' => 'dup@test.com',
            'password' => 'Secret123!',
        ], $this->authHeader());

        $response->assertStatus(422);
    }

    public function test_admin_can_show_agent(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-SHOW',
            'name' => 'Show Agent',
            'email' => 'show@test.com',
            'commission_rate' => 15,
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/admin/agents/{$agent->id}", $this->authHeader());
        $response->assertOk();
        $response->assertJsonFragment(['code' => 'AGT-SHOW']);
    }

    public function test_admin_can_update_agent(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-UPDATE',
            'name' => 'Update Agent',
            'email' => 'update@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/admin/agents/{$agent->id}", [
            'name' => 'Updated Agent',
            'commission_rate' => 20,
            'tier' => 'gold',
        ], $this->authHeader());

        $response->assertOk();
        $this->assertDatabaseHas('agents', [
            'id' => $agent->id,
            'name' => 'Updated Agent',
            'commission_rate' => 20,
            'tier' => 'gold',
        ], 'landlord');
    }

    public function test_admin_cannot_delete_agent_with_pending_commissions(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-NODEL',
            'name' => 'No Delete Agent',
            'email' => 'nodel@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        AgentCommission::create([
            'agent_id' => $agent->id,
            'amount' => 50,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $response = $this->deleteJson("/api/v1/admin/agents/{$agent->id}", [], $this->authHeader());
        $response->assertStatus(400);
        $response->assertJsonFragment(['error' => 'Cannot delete agent with pending commissions']);
    }

    public function test_admin_can_delete_agent_without_pending_commissions(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-DEL',
            'name' => 'Delete Agent',
            'email' => 'del@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/admin/agents/{$agent->id}", [], $this->authHeader());
        $response->assertOk();
        $this->assertSoftDeleted('agents', ['id' => $agent->id], 'landlord');
    }

    public function test_admin_agent_search(): void
    {
        Agent::create([
            'code' => 'AGT-SEARCH',
            'name' => 'Searchable Agent',
            'email' => 'searchable@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/admin/agents?search=searchable', $this->authHeader());
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_admin_agent_tier_filter(): void
    {
        Agent::create([
            'code' => 'AGT-GOLD',
            'name' => 'Gold Agent',
            'email' => 'gold@test.com',
            'commission_rate' => 10,
            'tier' => 'gold',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/admin/agents?tier=gold', $this->authHeader());
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_admin_agent_performance(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-PERF',
            'name' => 'Perf Agent',
            'email' => 'perf@test.com',
            'commission_rate' => 15,
            'is_active' => true,
            'total_earnings' => 500,
            'pending_earnings' => 200,
            'paid_earnings' => 300,
        ]);

        AgentReferral::create([
            'agent_id' => $agent->id,
            'referral_code' => 'AGT-PERF-REF1',
            'converted_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/admin/agents/{$agent->id}/performance", $this->authHeader());
        $response->assertOk();
        $response->assertJsonStructure([
            'referrals', 'commissions', 'conversion_rate', 'total_earnings', 'pending_earnings', 'paid_earnings',
        ]);
    }

    // --- Commission Management ---

    public function test_admin_can_list_commissions(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-COMM',
            'name' => 'Comm Agent',
            'email' => 'comm@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        AgentCommission::create([
            'agent_id' => $agent->id,
            'amount' => 100,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/admin/commissions', $this->authHeader());
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_admin_can_filter_commissions_by_status(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-FILTER',
            'name' => 'Filter Agent',
            'email' => 'filter@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        AgentCommission::create([
            'agent_id' => $agent->id,
            'amount' => 50,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        AgentCommission::create([
            'agent_id' => $agent->id,
            'amount' => 75,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'paid',
        ]);

        $response = $this->getJson('/api/v1/admin/commissions?status=pending', $this->authHeader());
        $response->assertOk();
        $this->assertGreaterThanOrEqual(1, count($response->json('data')));
    }

    public function test_admin_commission_summary(): void
    {
        $response = $this->getJson('/api/v1/admin/commissions/summary', $this->authHeader());
        $response->assertOk();
        $response->assertJsonStructure([
            'summary', 'total_pending', 'total_cleared', 'total_paid',
        ]);
    }

    public function test_admin_can_approve_commission(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-APPROVE',
            'name' => 'Approve Agent',
            'email' => 'approve@test.com',
            'commission_rate' => 10,
            'is_active' => true,
            'pending_earnings' => 50,
        ]);

        $commission = AgentCommission::create([
            'agent_id' => $agent->id,
            'amount' => 50,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/admin/commissions/{$commission->id}/approve", [], $this->authHeader());
        $response->assertOk();
        $response->assertJsonPath('status', 'cleared');

        $commission->refresh();
        $this->assertNotNull($commission->cleared_at);

        // Approve only changes status — pending_earnings unchanged
        $agent->refresh();
        $this->assertEquals(50, (float) $agent->pending_earnings);
    }

    public function test_admin_cannot_approve_non_pending_commission(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-APPROVED',
            'name' => 'Already Approved',
            'email' => 'approved@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        $commission = AgentCommission::create([
            'agent_id' => $agent->id,
            'amount' => 50,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'paid',
        ]);

        $response = $this->postJson("/api/v1/admin/commissions/{$commission->id}/approve", [], $this->authHeader());
        $response->assertStatus(400);
    }

    public function test_admin_can_pay_commission(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-PAY',
            'name' => 'Pay Agent',
            'email' => 'pay@test.com',
            'commission_rate' => 10,
            'is_active' => true,
            'pending_earnings' => 50,
        ]);

        $commission = AgentCommission::create([
            'agent_id' => $agent->id,
            'amount' => 50,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'cleared',
            'cleared_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/admin/commissions/{$commission->id}/pay", [
            'payout_reference' => 'PAY-12345',
        ], $this->authHeader());
        $response->assertOk();
        $response->assertJsonPath('status', 'paid');

        $commission->refresh();
        $this->assertNotNull($commission->paid_at);
        $this->assertEquals('PAY-12345', $commission->payout_reference);

        $agent->refresh();
        $this->assertEquals(0, (float) $agent->pending_earnings);
        $this->assertEquals(50, (float) $agent->paid_earnings);
    }

    public function test_admin_can_pay_pending_commission_directly(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-PAYP',
            'name' => 'Pay Pending Agent',
            'email' => 'payp@test.com',
            'commission_rate' => 10,
            'is_active' => true,
            'pending_earnings' => 50,
        ]);

        $commission = AgentCommission::create([
            'agent_id' => $agent->id,
            'amount' => 50,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/admin/commissions/{$commission->id}/pay", [], $this->authHeader());
        $response->assertOk();
        $response->assertJsonPath('status', 'paid');
    }

    public function test_admin_cannot_pay_rejected_commission(): void
    {
        $agent = Agent::create([
            'code' => 'AGT-REJ',
            'name' => 'Rejected Agent',
            'email' => 'rej@test.com',
            'commission_rate' => 10,
            'is_active' => true,
        ]);

        $commission = AgentCommission::create([
            'agent_id' => $agent->id,
            'amount' => 50,
            'rate' => 10,
            'type' => 'subscription_referral',
            'status' => 'rejected',
        ]);

        $response = $this->postJson("/api/v1/admin/commissions/{$commission->id}/pay", [], $this->authHeader());
        $response->assertStatus(400);
    }

    /** Test full admin lifecycle: create agent → approve commission → pay */
    public function test_admin_full_agent_lifecycle(): void
    {
        // Create agent (with AgentUser)
        $response = $this->postJson('/api/v1/admin/agents', [
            'name' => 'Lifecycle Agent',
            'email' => 'lifecycle@test.com',
            'password' => 'Secret123!',
            'commission_rate' => 20,
        ], $this->authHeader());
        $response->assertCreated();
        $response->assertJsonStructure(['agent' => ['id']]);
        $agentId = $response->json('agent.id');

        $this->assertDatabaseHas('agent_users', ['email' => 'lifecycle@test.com'], 'landlord');

        // Simulate CommissionService having set pending_earnings
        Agent::where('id', $agentId)->update(['pending_earnings' => 100]);

        // Create a commission
        $commission = AgentCommission::create([
            'agent_id' => $agentId,
            'amount' => 100,
            'rate' => 20,
            'type' => 'subscription_referral',
            'status' => 'pending',
        ]);

        // Approve — just changes status, earnings unchanged
        $response = $this->postJson("/api/v1/admin/commissions/{$commission->id}/approve", [], $this->authHeader());
        $response->assertOk();
        $this->assertDatabaseHas('agent_commissions', ['id' => $commission->id, 'status' => 'cleared'], 'landlord');

        $agent = Agent::find($agentId);
        $this->assertEquals(100, (float) $agent->pending_earnings);

        $response = $this->postJson("/api/v1/admin/commissions/{$commission->id}/pay", [
            'payout_reference' => 'PAY-LIFECYCLE',
        ], $this->authHeader());
        $response->assertOk();
        $this->assertDatabaseHas('agent_commissions', ['id' => $commission->id, 'status' => 'paid'], 'landlord');

        // Verify agent stats
        $agent = Agent::find($agentId);
        $this->assertEquals(0, (float) $agent->pending_earnings);
        $this->assertEquals(100, (float) $agent->total_earnings);
        $this->assertEquals(100, (float) $agent->paid_earnings);
    }
}
