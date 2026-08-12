<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Landlord\AdminUser;
use Illuminate\Support\Facades\Hash;
use Tests\SaaS;

class AdminProfileTest extends SaaS
{

    private function createAdmin(array $overrides = []): AdminUser
    {
        return AdminUser::create(array_merge([
            'name' => 'Super Admin',
            'email' => 'admin@classicpos.app',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ], $overrides));
    }

    private function loginAsAdmin(AdminUser $admin, string $password = 'password'): string
    {
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email,
            'password' => $password,
        ]);

        $response->assertOk();

        return $response->json('token');
    }

    private function createTokenFor(AdminUser $admin): string
    {
        return $admin->createToken('test-token', ['*'])->plainTextToken;
    }

    private function authHeaders(string $token): array
    {
        return [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];
    }

    // --- Profile GET ---

    public function test_admin_can_get_profile(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        $response = $this->withHeaders($this->authHeaders($token))
            ->getJson('/api/v1/admin/auth/profile');

        $response->assertOk();
        $response->assertJson([
            'id' => $admin->id,
            'name' => 'Super Admin',
            'email' => 'admin@classicpos.app',
            'role' => 'super_admin',
            'is_active' => true,
        ]);
        $response->assertJsonStructure(['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at']);
    }

    public function test_unauthenticated_cannot_get_profile(): void
    {
        $this->getJson('/api/v1/admin/auth/profile')
            ->assertUnauthorized();
    }

    // --- Profile UPDATE ---

    public function test_admin_can_update_name(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/profile', ['name' => 'Updated Name']);

        $response->assertOk();
        $response->json('name') === 'Updated Name';

        $admin->refresh();
        $this->assertEquals('Updated Name', $admin->name);
    }

    public function test_admin_can_update_email(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/profile', ['email' => 'newemail@classicpos.app']);

        $response->assertOk();

        $admin->refresh();
        $this->assertEquals('newemail@classicpos.app', $admin->email);
    }

    public function test_admin_cannot_update_to_existing_email(): void
    {
        $admin1 = $this->createAdmin(['email' => 'admin1@classicpos.app']);
        $this->createAdmin(['email' => 'admin2@classicpos.app', 'name' => 'Other Admin']);
        $token = $this->loginAsAdmin($admin1);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/profile', ['email' => 'admin2@classicpos.app']);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'The email has already been taken.']);
    }

    public function test_admin_can_update_name_and_email_together(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/profile', [
                'name' => 'Both Updated',
                'email' => 'both@classicpos.app',
            ]);

        $response->assertOk();

        $admin->refresh();
        $this->assertEquals('Both Updated', $admin->name);
        $this->assertEquals('both@classicpos.app', $admin->email);
    }

    public function test_profile_update_validates_email_format(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/profile', ['email' => 'not-an-email']);

        $response->assertStatus(422);
    }

    // --- Password CHANGE ---

    public function test_admin_can_change_password(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/change-password', [
                'current_password' => 'password',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertOk();
        $response->assertJson(['message' => 'Password updated successfully.']);

        $admin->refresh();
        $this->assertTrue(Hash::check('newpassword123', $admin->password));
        $this->assertFalse(Hash::check('password', $admin->password));
    }

    public function test_admin_cannot_change_password_with_wrong_current(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/change-password', [
                'current_password' => 'wrongpassword',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ]);

        $response->assertStatus(422);
        $response->assertJson(['error' => 'Current password is incorrect.']);
    }

    public function test_password_change_requires_confirmation(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/change-password', [
                'current_password' => 'password',
                'password' => 'newpassword123',
            ]);

        $response->assertStatus(422);
    }

    public function test_password_change_requires_minimum_length(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        $response = $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/change-password', [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]);

        $response->assertStatus(422);
    }

    public function test_admin_can_login_with_new_password_after_change(): void
    {
        $admin = $this->createAdmin();
        $token = $this->createTokenFor($admin);

        // Change password
        $this->withHeaders($this->authHeaders($token))
            ->putJson('/api/v1/admin/auth/change-password', [
                'current_password' => 'password',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])->assertOk();

        // Verify old password doesn't work
        $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@classicpos.app',
            'password' => 'password',
        ])->assertStatus(401);

        // Verify new password works via direct login
        $loginResponse = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@classicpos.app',
            'password' => 'newpassword123',
        ]);

        $loginResponse->assertOk();
        $this->assertNotEmpty($loginResponse->json('token'));
    }

    public function test_unauthenticated_cannot_change_password(): void
    {
        $this->putJson('/api/v1/admin/auth/change-password', [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertUnauthorized();
    }
}
