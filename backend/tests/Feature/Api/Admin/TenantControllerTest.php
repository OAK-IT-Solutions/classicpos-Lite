<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Branch;
use App\Models\Landlord\Tenant;
use App\Models\Role;
use App\Models\User;
use Tests\SaaS;

class TenantControllerTest extends SaaS
{

    private User $admin;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        config(['landlord.self_hosted' => true]);

        $branch = Branch::create(['name' => 'HQ', 'location' => 'Nairobi', 'timezone' => 'Africa/Nairobi']);
        $this->admin = User::factory()->create();
        $role = Role::create(['name' => 'admin']);
        $this->admin->roles()->attach($role->id, ['branch_id' => $branch->id]);

        $this->tenant = Tenant::create([
            'name' => 'Test Business',
            'slug' => 'test-business',
            'status' => 'active',
        ]);
    }

    private function authHeader(): array
    {
        $token = $this->admin->createToken('test')->plainTextToken;
        return ['Authorization' => "Bearer $token"];
    }

    public function test_can_list_tenants(): void
    {
        $response = $this->getJson('/api/v1/admin/tenants', $this->authHeader());
        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_can_show_tenant(): void
    {
        $response = $this->getJson("/api/v1/admin/tenants/{$this->tenant->id}", $this->authHeader());
        $response->assertOk();
        $response->assertJsonFragment(['id' => $this->tenant->id]);
    }

    public function test_non_admin_cannot_access(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->getJson('/api/v1/admin/tenants', ['Authorization' => "Bearer $token"]);
        $response->assertForbidden();
    }
}
