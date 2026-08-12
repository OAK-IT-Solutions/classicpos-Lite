<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Branch;
use App\Models\Landlord\PlatformSetting;
use App\Models\Role;
use App\Models\User;
use Tests\SaaS;

class PlatformSettingControllerTest extends SaaS
{

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        config(['landlord.self_hosted' => true]);

        $branch = Branch::create(['name' => 'HQ', 'location' => 'Nairobi', 'timezone' => 'Africa/Nairobi']);
        $this->admin = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        $this->admin->roles()->attach($role->id, ['branch_id' => $branch->id]);
    }

    private function authHeader(): array
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    public function test_can_list_settings(): void
    {
        PlatformSetting::create(['key' => 'site_name', 'value' => 'ClassicPOS', 'group' => 'general']);

        $response = $this->getJson('/api/v1/admin/settings', $this->authHeader());
        $response->assertOk();
    }

    public function test_can_update_settings(): void
    {
        PlatformSetting::create(['key' => 'site_name', 'value' => 'Old Name', 'group' => 'general']);

        $response = $this->putJson('/api/v1/admin/settings', [
            'settings' => [
                ['key' => 'site_name', 'value' => 'New Name'],
            ],
        ], $this->authHeader());

        $response->assertOk();
        $this->assertDatabaseHas('platform_settings', ['key' => 'site_name', 'value' => 'New Name'], 'landlord');
    }
}
