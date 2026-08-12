<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Landlord\AdminUser;
use Illuminate\Support\Facades\Hash;
use Tests\SaaS;

class AdminLoginTest extends SaaS
{

    public function test_admin_can_login(): void
    {
        AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@classicpos.app',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@classicpos.app',
            'password' => 'password',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['token', 'user']);
        $response->assertJsonPath('user.role', 'super_admin');
    }

    public function test_admin_login_fails_with_wrong_password(): void
    {
        AdminUser::create([
            'name' => 'Super Admin',
            'email' => 'admin@classicpos.app',
            'password' => Hash::make('password'),
            'role' => 'super_admin',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'admin@classicpos.app',
            'password' => 'wrongpassword',
        ]);

        $response->assertUnauthorized();
    }
}
