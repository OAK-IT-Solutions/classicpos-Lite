<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Landlord\AgentCommission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AgentCommissionController extends Controller
{
    #[OA\Get(path: "/agent/commissions", tags: ["Agent"], summary: "List agent commissions", responses: [new OA\Response(response: 200, description: "Commissions listed")])]
    public function index(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $query = AgentCommission::where('agent_id', $agent->id)
            ->with('tenant:id,name');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('tenant', fn($t) => $t->where('name', 'ilike', "%{$search}%"));
        }

        $commissions = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($commissions);
    }

    #[OA\Get(path: "/agent/commissions/summary", tags: ["Agent"], summary: "Commission summary", responses: [new OA\Response(response: 200, description: "Summary returned")])]
    public function summary(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $summary = [
            'total_earned' => $agent->total_earnings,
            'pending' => $agent->pending_earnings,
            'paid' => $agent->paid_earnings,
            'this_month' => AgentCommission::where('agent_id', $agent->id)
                ->where('created_at', '>=', now()->startOfMonth())
                ->sum('amount'),
            'last_month' => AgentCommission::where('agent_id', $agent->id)
                ->where('created_at', '>=', now()->subMonth()->startOfMonth())
                ->where('created_at', '<', now()->startOfMonth())
                ->sum('amount'),
            'by_status' => AgentCommission::where('agent_id', $agent->id)
                ->select('status', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->groupBy('status')
                ->get()
                ->pluck('total', 'status')
                ->toArray(),
            'by_type' => AgentCommission::where('agent_id', $agent->id)
                ->select('type', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
                ->groupBy('type')
                ->get()
                ->pluck('total', 'type')
                ->toArray(),
        ];

        return response()->json($summary);
    }

    #[OA\Get(path: "/agent/commissions/{id}", tags: ["Agent"], summary: "Get commission", responses: [new OA\Response(response: 200, description: "Commission returned")])]
    public function show(Request $request, string $id): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $commission = AgentCommission::where('agent_id', $agent->id)
            ->with('tenant:id,name,slug')
            ->findOrFail($id);

        return response()->json([
            'id' => $commission->id,
            'amount' => $commission->amount,
            'rate' => $commission->rate,
            'type' => $commission->type,
            'status' => $commission->status,
            'tenant_name' => $commission->tenant?->name,
            'tenant_slug' => $commission->tenant?->slug,
            'notes' => $commission->notes,
            'cleared_at' => $commission->cleared_at?->toIso8601String(),
            'paid_at' => $commission->paid_at?->toIso8601String(),
            'payout_reference' => $commission->payout_reference,
            'created_at' => $commission->created_at->toIso8601String(),
        ]);
    }
}
