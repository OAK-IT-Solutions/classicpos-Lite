<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

class CommissionController extends Controller
{
    #[OA\Get(path: "/admin/commissions", tags: ["Admin Commissions"], summary: "List commissions", responses: [new OA\Response(response: 200, description: "Commissions listed")])]
    public function index(Request $request): JsonResponse
    {
        $query = AgentCommission::with('agent', 'tenant')
            ->when($request->status, fn ($q, $s) => $q->where('status', $s))
            ->when($request->agent_id, fn ($q, $a) => $q->where('agent_id', $a));

        $commissions = $query->orderByDesc('created_at')->paginate($request->per_page ?? 20);

        return response()->json($commissions);
    }

    #[OA\Get(path: "/admin/commissions/summary", tags: ["Admin Commissions"], summary: "Commission summary", responses: [new OA\Response(response: 200, description: "Summary returned")])]
    public function summary(): JsonResponse
    {
        $summary = AgentCommission::selectRaw("
            status,
            count(*) as count,
            sum(amount) as total_amount
        ")
            ->groupBy('status')
            ->get();

        $totalPending = AgentCommission::where('status', 'pending')->sum('amount');
        $totalCleared = AgentCommission::where('status', 'cleared')->sum('amount');
        $totalPaid = AgentCommission::where('status', 'paid')->sum('amount');

        return response()->json([
            'summary' => $summary,
            'total_pending' => (float) $totalPending,
            'total_cleared' => (float) $totalCleared,
            'total_paid' => (float) $totalPaid,
        ]);
    }

    #[OA\Post(path: "/admin/commissions/{commission}/approve", tags: ["Admin Commissions"], summary: "Approve commission", responses: [new OA\Response(response: 200, description: "Commission approved")])]
    public function approve(AgentCommission $commission): JsonResponse
    {
        if ($commission->status !== 'pending') {
            return response()->json(['error' => 'Commission is not pending'], 400);
        }

        $commission->update([
            'status' => 'cleared',
            'cleared_at' => now(),
        ]);

        $agent = Agent::find($commission->agent_id);
        AuditLog::log('commission.approve', 'agent', 'AgentCommission', $commission->id, "Commission approved for {$agent->name}");

        return response()->json($commission->fresh()->load('agent'));
    }

    #[OA\Post(path: "/admin/commissions/{commission}/pay", tags: ["Admin Commissions"], summary: "Pay commission", responses: [new OA\Response(response: 200, description: "Commission paid")])]
    public function pay(AgentCommission $commission): JsonResponse
    {
        if (!in_array($commission->status, ['pending', 'cleared'])) {
            return response()->json(['error' => 'Commission cannot be paid in current status'], 400);
        }

        $data = request()->validate([
            'payout_reference' => 'nullable|string|max:255',
        ]);

        $commission->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payout_reference' => $data['payout_reference'] ?? null,
        ]);

        // Update agent
        $agent = Agent::find($commission->agent_id);
        $agent->decrement('pending_earnings', $commission->amount);
        $agent->increment('paid_earnings', $commission->amount);
        $agent->increment('total_earnings', $commission->amount);

        AuditLog::log('commission.pay', 'agent', 'AgentCommission', $commission->id, "Commission paid to {$agent->name}");

        return response()->json($commission->fresh()->load('agent'));
    }
}
