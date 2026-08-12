<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\AgentReferral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use OpenApi\Attributes as OA;

class AgentDashboardController extends Controller
{
    #[OA\Get(path: "/agent/dashboard", tags: ["Agent"], summary: "Agent dashboard", responses: [new OA\Response(response: 200, description: "Dashboard data returned")])]
    public function index(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $stats = [
            'overview' => [
                'total_referrals' => $agent->total_referrals,
                'converted_referrals' => $agent->converted_referrals,
                'conversion_rate' => $agent->conversion_rate,
                'tier' => $agent->tier,
                'tier_label' => $agent->tierLabel(),
                'commission_rate' => $agent->commission_rate,
            ],
            'earnings' => [
                'total_earnings' => $agent->total_earnings,
                'pending_earnings' => $agent->pending_earnings,
                'paid_earnings' => $agent->paid_earnings,
            ],
            'recent_commissions' => AgentCommission::where('agent_id', $agent->id)
                ->with('tenant:id,name')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn($c) => [
                    'id' => $c->id,
                    'amount' => $c->amount,
                    'type' => $c->type,
                    'status' => $c->status,
                    'tenant_name' => $c->tenant?->name,
                    'created_at' => $c->created_at->toIso8601String(),
                ]),
            'recent_referrals' => AgentReferral::where('agent_id', $agent->id)
                ->with('tenant:id,name')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get()
                ->map(fn($r) => [
                    'id' => $r->id,
                    'referral_code' => $r->referral_code,
                    'status' => $r->converted_at ? 'converted' : ($r->registered_at ? 'registered' : 'clicked'),
                    'tenant_name' => $r->tenant?->name,
                    'commission_earned' => $r->commission_earned,
                    'created_at' => $r->created_at->toIso8601String(),
                ]),
            'monthly_earnings' => AgentCommission::where('agent_id', $agent->id)
                ->where('created_at', '>=', now()->subMonths(12)->startOfMonth())
                ->select(
                    DB::raw("DATE_TRUNC('month', created_at) as month"),
                    DB::raw('SUM(amount) as total'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get()
                ->map(fn($m) => [
                    'month' => $m->month instanceof \Carbon\Carbon
                        ? $m->month->format('Y-m')
                        : \Carbon\Carbon::parse($m->month)->format('Y-m'),
                    'total' => round($m->total, 2),
                    'count' => (int) $m->count,
                ]),
        ];

        return response()->json($stats);
    }

    #[OA\Get(path: "/agent/dashboard/profile", tags: ["Agent"], summary: "Agent profile", responses: [new OA\Response(response: 200, description: "Profile returned")])]
    public function profile(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        return response()->json([
            'id' => $agent->id,
            'code' => $agent->code,
            'name' => $agent->name,
            'email' => $agent->email,
            'phone' => $agent->phone,
            'tier' => $agent->tier,
            'tier_label' => $agent->tierLabel(),
            'commission_rate' => $agent->commission_rate,
            'is_active' => $agent->is_active,
            'activated_at' => $agent->activated_at?->toIso8601String(),
            'total_referrals' => $agent->total_referrals,
            'converted_referrals' => $agent->converted_referrals,
            'total_earnings' => $agent->total_earnings,
            'pending_earnings' => $agent->pending_earnings,
            'paid_earnings' => $agent->paid_earnings,
        ]);
    }
}
