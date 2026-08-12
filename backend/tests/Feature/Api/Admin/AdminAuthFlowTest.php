<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Landlord\AdminUser;
use Illuminate\Support\Facades\Hash;
use Tests\SaaS;

class AdminAuthFlowTest extends SaaS
{

    private function createAdmin(): AdminUser
    {
        return AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@classicpos.app',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_login_and_access_protected_endpoints(): void
    {
        $this->createAdmin();

        // Step 1: Login
        $loginResponse = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@classicpos.app',
            'password' => 'password',
        ]);

        $loginResponse->assertOk();
        $loginResponse->assertJsonStructure(['token', 'user']);
        $token = $loginResponse->json('token');
        $this->assertNotEmpty($token);

        // Step 2: Use token to access /me endpoint
        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/auth/me');

        $meResponse->assertOk();
        $meResponse->assertJson([
            'id' => $loginResponse->json('user.id'),
            'name' => 'Super Admin',
            'email' => 'admin@classicpos.app',
            'role' => 'super_admin',
        ]);

        // Step 3: Use token to access dashboard endpoint
        $dashboardResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/dashboard');

        $dashboardResponse->assertOk();
        $dashboardResponse->assertJsonStructure([
            'tenants', 'mrr', 'last_month_mrr', 'mrr_growth',
            'revenue_this_month', 'revenue_this_year',
            'active_subscriptions', 'open_tickets', 'pending_commissions',
        ]);

        // Step 4: Use token to access settings endpoint
        $settingsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/settings');

        $settingsResponse->assertOk();

        // Step 5: Use token to access tenants endpoint
        $tenantsResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/tenants');

        $tenantsResponse->assertOk();

        // Step 6: Use the same token again after multiple requests
        $meAgainResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/auth/me');

        $meAgainResponse->assertOk();
    }

    public function test_admin_token_is_valid_across_consecutive_requests(): void
    {
        $this->createAdmin();

        $loginResponse = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@classicpos.app',
            'password' => 'password',
        ]);
        $token = $loginResponse->json('token');

        // Make 10 consecutive requests to verify token stability
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->getJson('/api/v1/admin/auth/me');

            $response->assertOk();
        }
    }
}
