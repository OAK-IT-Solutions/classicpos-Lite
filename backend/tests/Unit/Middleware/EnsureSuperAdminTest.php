<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureSuperAdmin;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tests\SaaS;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdminTest extends SaaS
{

    private EnsureSuperAdmin $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureSuperAdmin();
    }

    private function makeRequest(?User $user): Request
    {
        $request = Request::create('/test', 'GET');
        if ($user) {
            $request->setUserResolver(fn() => $user);
        }
        return $request;
    }

    private function createAdmin(): User
    {
        $branch = Branch::create(['name' => 'HQ', 'location' => 'Nairobi', 'timezone' => 'Africa/Nairobi']);
        $user = User::factory()->create();
        $role = \App\Models\Role::create(['name' => 'admin']);
        $user->roles()->attach($role->id, ['branch_id' => $branch->id]);
        return $user;
    }

    private function createSuperAdmin(): User
    {
        $branch = Branch::create(['name' => 'HQ', 'location' => 'Nairobi', 'timezone' => 'Africa/Nairobi']);
        $user = User::factory()->create();
        $role = \App\Models\Role::create(['name' => 'super_admin']);
        $user->roles()->attach($role->id, ['branch_id' => $branch->id]);
        return $user;
    }

    private function createCashier(): User
    {
        $branch = Branch::create(['name' => 'HQ', 'location' => 'Nairobi', 'timezone' => 'Africa/Nairobi']);
        $user = User::factory()->create();
        $role = \App\Models\Role::create(['name' => 'cashier']);
        $user->roles()->attach($role->id, ['branch_id' => $branch->id]);
        return $user;
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $request = $this->makeRequest(null);
        $response = $this->middleware->handle($request, fn() => new JsonResponse(['ok']));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_non_admin_user_gets_403_in_saas_mode(): void
    {
        config(['landlord.self_hosted' => false]);

        $user = $this->createCashier();
        $request = $this->makeRequest($user);
        $response = $this->middleware->handle($request, fn() => new JsonResponse(['ok']));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_super_admin_user_passes_in_saas_mode(): void
    {
        config(['landlord.self_hosted' => false]);

        $user = $this->createSuperAdmin();
        $request = $this->makeRequest($user);
        $response = $this->middleware->handle($request, fn() => new JsonResponse(['ok']));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_admin_user_passes_in_self_hosted_mode(): void
    {
        config(['landlord.self_hosted' => true]);

        $user = $this->createAdmin();
        $request = $this->makeRequest($user);
        $response = $this->middleware->handle($request, fn() => new JsonResponse(['ok']));

        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_non_admin_user_gets_403_in_self_hosted_mode(): void
    {
        config(['landlord.self_hosted' => true]);

        $user = $this->createCashier();
        $request = $this->makeRequest($user);
        $response = $this->middleware->handle($request, fn() => new JsonResponse(['ok']));

        $this->assertEquals(403, $response->getStatusCode());
    }
}
