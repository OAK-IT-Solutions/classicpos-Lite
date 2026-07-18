<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentUser;
use App\Models\Landlord\AgentReferral;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\AuditLog;
use App\Models\Landlord\PlatformSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Rules\ComplexPassword;
use OpenApi\Attributes as OA;

class AgentController extends Controller
{
    #[OA\Get(path: "/admin/agents", tags: ["Admin Agents"], summary: "List agents", responses: [new OA\Response(response: 200, description: "Agents listed")])]
    public function index(Request $request): JsonResponse
    {
        $query = Agent::withCount('referrals', 'commissions')
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('name', 'ilike', "%{$s}%")->orWhere('email', 'ilike', "%{$s}%")->orWhere('code', 'ilike', "%{$s}%")))
            ->when($request->tier, fn ($q, $t) => $q->where('tier', $t))
            ->when($request->is_active !== null, fn ($q) => $q->where('is_active', $request->boolean('is_active')));

        $agents = $query->orderByDesc('created_at')->paginate($request->per_page ?? 20);

        return response()->json($agents);
    }

    #[OA\Post(path: "/admin/agents", tags: ["Admin Agents"], summary: "Create agent", responses: [new OA\Response(response: 201, description: "Agent created")])]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'phone' => 'nullable|string|max:50',
            'password' => ['required', 'string', new ComplexPassword],
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'tier' => 'sometimes|in:standard,silver,gold,platinum',
        ]);

        // Check email uniqueness across AgentUser and Agent
        if (AgentUser::where('email', $data['email'])->exists()) {
            return response()->json(['error' => 'The email has already been taken.'], 422);
        }
        if (DB::connection('landlord')->table('agents')->where('email', $data['email'])->exists()) {
            return response()->json(['error' => 'The email has already been taken.'], 422);
        }

        // Create AgentUser so the agent can log in
        $agentUser = AgentUser::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'is_active' => true,
        ]);

        $agentCode = strtoupper('AG-' . Str::random(6));

        $agent = Agent::create([
            'user_id' => $agentUser->id,
            'code' => $agentCode,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'commission_rate' => $data['commission_rate'] ?? PlatformSetting::get('agent_default_commission_rate', 15),
            'tier' => $data['tier'] ?? 'standard',
            'is_active' => true,
            'activated_at' => now(),
        ]);

        AuditLog::log('agent.create', 'agent', 'Agent', $agent->id, $agent->name);

        return response()->json([
            'agent' => $agent,
        ], 201);
    }

    #[OA\Get(path: "/admin/agents/{agent}", tags: ["Admin Agents"], summary: "Get agent", responses: [new OA\Response(response: 200, description: "Agent returned")])]
    public function show(Agent $agent): JsonResponse
    {
        $agent->load([
            'commissions' => fn ($q) => $q->latest()->limit(10),
            'referrals' => fn ($q) => $q->latest()->limit(20),
        ]);
        $agent->loadCount('referrals', 'commissions');

        return response()->json($agent);
    }

    #[OA\Put(path: "/admin/agents/{agent}", tags: ["Admin Agents"], summary: "Update agent", responses: [new OA\Response(response: 200, description: "Agent updated")])]
    public function update(Request $request, Agent $agent): JsonResponse
    {
        $old = $agent->toArray();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'nullable|string|max:50',
            'commission_rate' => 'sometimes|numeric|min:0|max:100',
            'tier' => 'sometimes|in:standard,silver,gold,platinum',
            'is_active' => 'boolean',
        ]);

        $agent->update($data);

        AuditLog::log('agent.update', 'agent', 'Agent', $agent->id, $agent->name, $old, $agent->fresh()->toArray());

        return response()->json($agent->fresh());
    }

    #[OA\Delete(path: "/admin/agents/{agent}", tags: ["Admin Agents"], summary: "Delete agent", responses: [new OA\Response(response: 200, description: "Agent deleted")])]
    public function destroy(Agent $agent): JsonResponse
    {
        if ($agent->commissions()->where('status', 'pending')->exists()) {
            return response()->json(['error' => 'Cannot delete agent with pending commissions'], 400);
        }

        AuditLog::log('agent.delete', 'agent', 'Agent', $agent->id, $agent->name);
        $agent->delete();

        return response()->json(['message' => 'Agent deleted']);
    }

    #[OA\Get(path: "/admin/agents/{agent}/performance", tags: ["Admin Agents"], summary: "Agent performance", responses: [new OA\Response(response: 200, description: "Performance data returned")])]
    public function performance(Agent $agent): JsonResponse
    {
        $months = request()->months ?? 6;

        $referrals = AgentReferral::where('agent_id', $agent->id)
            ->where('created_at', '>=', now()->subMonths($months))
            ->selectRaw("date_trunc('month', created_at) as month, count(*) as total, count(converted_at) as converted")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $commissions = AgentCommission::where('agent_id', $agent->id)
            ->where('created_at', '>=', now()->subMonths($months))
            ->selectRaw("date_trunc('month', created_at) as month, sum(amount) as total, status")
            ->groupBy('month', 'status')
            ->orderBy('month')
            ->get();

        return response()->json([
            'referrals' => $referrals,
            'commissions' => $commissions,
            'conversion_rate' => $agent->conversion_rate,
            'total_earnings' => $agent->total_earnings,
            'pending_earnings' => $agent->pending_earnings,
            'paid_earnings' => $agent->paid_earnings,
        ]);
    }
}
