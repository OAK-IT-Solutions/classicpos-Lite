<?php

namespace Tests\Feature\Api\Admin;

use App\Models\Branch;
use App\Models\Landlord\AuditLog;
use App\Models\Role;
use App\Models\User;
use Tests\SaaS;

class AuditLogControllerTest extends SaaS
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

    public function test_can_list_audit_logs(): void
    {
        AuditLog::create([
            'user_id' => $this->admin->id,
            'user_type' => 'admin',
            'action' => 'tenant.created',
            'subject_type' => 'Tenant',
            'subject_id' => 'test-id',
            'metadata' => ['name' => 'Test'],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->getJson('/api/v1/admin/audit-logs', $this->authHeader());
        $response->assertOk();
        $response->assertJsonFragment(['action' => 'tenant.created']);
    }
}
