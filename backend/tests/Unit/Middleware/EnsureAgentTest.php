<?php

namespace Tests\Unit\Middleware;

use App\Http\Middleware\EnsureAgent;
use App\Models\Landlord\Agent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Tests\SaaS;

class EnsureAgentTest extends SaaS
{

    private EnsureAgent $middleware;

    protected function setUp(): void
    {
        parent::setUp();
        $this->middleware = new EnsureAgent();
    }

    private function makeRequest(?User $user): Request
    {
        $request = Request::create('/test', 'GET');
        if ($user) {
            $request->setUserResolver(fn() => $user);
        }
        return $request;
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        config(['landlord.self_hosted' => false]);

        $request = $this->makeRequest(null);
        $response = $this->middleware->handle($request, fn() => new JsonResponse(['ok']));

        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_self_hosted_mode_returns_404(): void
    {
        config(['landlord.self_hosted' => true]);

        $user = User::factory()->create();
        $request = $this->makeRequest($user);
        $response = $this->middleware->handle($request, fn() => new JsonResponse(['ok']));

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_user_without_agent_profile_gets_403(): void
    {
        config(['landlord.self_hosted' => false]);

        $user = User::factory()->create();
        $request = $this->makeRequest($user);
        $response = $this->middleware->handle($request, fn() => new JsonResponse(['ok']));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_inactive_agent_gets_403(): void
    {
        config(['landlord.self_hosted' => false]);

        $user = User::factory()->create();
        Agent::create([
            'user_id' => $user->id,
            'code' => 'AGENT001',
            'name' => 'Test Agent',
            'email' => $user->email,
            'commission_rate' => 15,
            'tier' => 'standard',
            'is_active' => false,
        ]);

        $request = $this->makeRequest($user);
        $response = $this->middleware->handle($request, fn() => new JsonResponse(['ok']));

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function test_active_agent_passes(): void
    {
        config(['landlord.self_hosted' => false]);

        $user = User::factory()->create();
        $agent = Agent::create([
            'user_id' => $user->id,
            'code' => 'AGENT001',
            'name' => 'Test Agent',
            'email' => $user->email,
            'commission_rate' => 15,
            'tier' => 'standard',
            'is_active' => true,
        ]);

        $request = $this->makeRequest($user);
        $response = $this->middleware->handle($request, function ($req) {
            $this->assertNotNull($req->attributes->get('agent'));
            return new JsonResponse(['ok']);
        });

        $this->assertEquals(200, $response->getStatusCode());
    }
}
