<?php

namespace Tests\Feature\Api\Agent;

use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentUser;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\SaaS;

class AgentProfileTest extends SaaS
{

    private function createAgentUser(array $overrides = []): AgentUser
    {
        return AgentUser::create(array_merge([
            'name' => 'Test Agent',
            'email' => 'agent@test.com',
            'password' => Hash::make('agent12345'),
            'is_active' => true,
        ], $overrides));
    }

    private function createAgentProfile(AgentUser $user, array $overrides = []): Agent
    {
        return Agent::create(array_merge([
            'user_id' => $user->id,
            'code' => strtoupper('AG-' . Str::random(6)),
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '+1234567890',
            'tier' => 'standard',
            'commission_rate' => 10.00,
            'is_active' => true,
            'activated_at' => now(),
        ], $overrides));
    }

    private function loginAsAgent(AgentUser $user): string
    {
        $response = $this->postJson('/api/v1/agent/auth/login', [
            'email' => $user->email,
            'password' => 'agent12345',
        ]);

        return $response->json('token');
    }

    private function createTokenFor(AgentUser $user): string
    {
        return $user->createToken('test-token', ['*'])->plainTextToken;
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    // --- Profile GET ---

    public function test_agent_can_get_profile(): void
    {
        $user = $this->createAgentUser();
        $agent = $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/agent/auth/profile');

        $response->assertOk();
        $response->assertJsonStructure([
            'user' => ['id', 'name', 'email', 'created_at'],
            'agent' => ['id', 'code', 'name', 'email', 'phone', 'tier', 'commission_rate', 'is_active',
                'total_referrals', 'converted_referrals', 'total_earnings', 'pending_earnings', 'paid_earnings', 'created_at'],
        ]);
        $response->assertJson([
            'user' => ['name' => 'Test Agent', 'email' => 'agent@test.com'],
            'agent' => ['phone' => '+1234567890', 'tier' => 'standard', 'commission_rate' => 10.0],
        ]);
    }

    public function test_unauthenticated_cannot_get_agent_profile(): void
    {
        $this->getJson('/api/v1/agent/auth/profile')
            ->assertUnauthorized();
    }

    // --- Profile UPDATE ---

    public function test_agent_can_update_name(): void
    {
        $user = $this->createAgentUser();
        $agent = $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/profile', ['name' => 'Updated Agent']);

        $response->assertOk();
        $response->assertJson([
            'user' => ['name' => 'Updated Agent'],
            'agent' => ['name' => 'Updated Agent'],
        ]);

        $user->refresh();
        $agent->refresh();
        $this->assertEquals('Updated Agent', $user->name);
        $this->assertEquals('Updated Agent', $agent->name);
    }

    public function test_agent_can_update_phone(): void
    {
        $user = $this->createAgentUser();
        $agent = $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/profile', ['phone' => '+9876543210']);

        $response->assertOk();
        $response->assertJson(['agent' => ['phone' => '+9876543210']]);

        $agent->refresh();
        $this->assertEquals('+9876543210', $agent->phone);
    }

    public function test_agent_can_update_email(): void
    {
        $user = $this->createAgentUser();
        $agent = $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/profile', ['email' => 'newemail@test.com']);

        $response->assertOk();

        $user->refresh();
        $agent->refresh();
        $this->assertEquals('newemail@test.com', $user->email);
        $this->assertEquals('newemail@test.com', $agent->email);
    }

    public function test_agent_cannot_update_to_existing_email(): void
    {
        $user1 = $this->createAgentUser(['email' => 'agent1@test.com']);
        $this->createAgentProfile($user1);

        $user2 = $this->createAgentUser(['email' => 'agent2@test.com', 'name' => 'Other Agent']);
        $this->createAgentProfile($user2);

        $token = $this->createTokenFor($user1);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/profile', ['email' => 'agent2@test.com']);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'The email has already been taken.']);
    }

    public function test_agent_can_update_all_fields_together(): void
    {
        $user = $this->createAgentUser();
        $agent = $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/profile', [
                'name' => 'All Updated',
                'email' => 'allupdated@test.com',
                'phone' => '+1112223333',
            ]);

        $response->assertOk();

        $user->refresh();
        $agent->refresh();
        $this->assertEquals('All Updated', $user->name);
        $this->assertEquals('allupdated@test.com', $user->email);
        $this->assertEquals('All Updated', $agent->name);
        $this->assertEquals('allupdated@test.com', $agent->email);
        $this->assertEquals('+1112223333', $agent->phone);
    }

    public function test_profile_update_validates_email_format(): void
    {
        $user = $this->createAgentUser();
        $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/profile', ['email' => 'not-an-email']);

        $response->assertStatus(422);
    }

    // --- Password CHANGE ---

    public function test_agent_can_change_password(): void
    {
        $user = $this->createAgentUser();
        $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/change-password', [
                'current_password' => 'agent12345',
                'password' => 'newagentpass123',
                'password_confirmation' => 'newagentpass123',
            ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Password updated successfully.']);

        $user->refresh();
        $this->assertTrue(Hash::check('newagentpass123', $user->password));
        $this->assertFalse(Hash::check('agent12345', $user->password));
    }

    public function test_agent_cannot_change_password_with_wrong_current(): void
    {
        $user = $this->createAgentUser();
        $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/change-password', [
                'current_password' => 'wrongpassword',
                'password' => 'newagentpass123',
                'password_confirmation' => 'newagentpass123',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Current password is incorrect.']);
    }

    public function test_password_change_requires_confirmation(): void
    {
        $user = $this->createAgentUser();
        $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/change-password', [
                'current_password' => 'agent12345',
                'password' => 'newagentpass123',
            ]);

        $response->assertStatus(422);
    }

    public function test_password_change_requires_minimum_length(): void
    {
        $user = $this->createAgentUser();
        $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/change-password', [
                'current_password' => 'agent12345',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertStatus(422);
    }

    public function test_agent_can_login_with_new_password_after_change(): void
    {
        $user = $this->createAgentUser();
        $this->createAgentProfile($user);
        $token = $this->createTokenFor($user);

        // Change password
        $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/agent/auth/change-password', [
                'current_password' => 'agent12345',
                'password' => 'newagentpass123',
                'password_confirmation' => 'newagentpass123',
            ])->assertOk();

        // Verify old password doesn't work
        $this->postJson('/api/v1/agent/auth/login', [
            'email' => 'agent@test.com',
            'password' => 'agent12345',
        ])->assertStatus(401);

        // Verify new password works via direct login
        $loginResponse = $this->postJson('/api/v1/agent/auth/login', [
            'email' => 'agent@test.com',
            'password' => 'newagentpass123',
        ]);

        $loginResponse->assertOk();
        $this->assertNotEmpty($loginResponse->json('token'));
    }

    public function test_unauthenticated_cannot_change_agent_password(): void
    {
        $this->putJson('/api/v1/agent/auth/change-password', [
            'current_password' => 'agent12345',
            'password' => 'newagentpass123',
            'password_confirmation' => 'newagentpass123',
        ])->assertUnauthorized();
    }

    public function test_agent_profile_returns_404_without_agent_profile(): void
    {
        $user = $this->createAgentUser();
        $token = $this->createTokenFor($user);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/agent/auth/profile');

        $response->assertStatus(404);
    }
}
