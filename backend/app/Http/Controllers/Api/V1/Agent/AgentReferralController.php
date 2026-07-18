<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Controllers\Controller;
use App\Models\Landlord\AgentReferral;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

class AgentReferralController extends Controller
{
    #[OA\Get(path: "/agent/referrals", tags: ["Agent"], summary: "List agent referrals", responses: [new OA\Response(response: 200, description: "Referrals listed")])]
    public function index(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $query = AgentReferral::where('agent_id', $agent->id)
            ->with('tenant:id,name');

        if ($status = $request->input('status')) {
            $query->where(function ($q) use ($status) {
                match ($status) {
                    'created' => $q->whereNull('clicked_at'),
                    'clicked' => $q->whereNotNull('clicked_at')->whereNull('registered_at'),
                    'registered' => $q->whereNotNull('registered_at')->whereNull('converted_at'),
                    'converted' => $q->whereNotNull('converted_at'),
                    default => null,
                };
            });
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('referral_code', 'ilike', "%{$search}%")
                  ->orWhereHas('tenant', fn($t) => $t->where('name', 'ilike', "%{$search}%"));
            });
        }

        $referrals = $query->orderByDesc('created_at')
            ->paginate($request->input('per_page', 15));

        return response()->json($referrals);
    }

    #[OA\Post(path: "/agent/referrals", tags: ["Agent"], summary: "Create referral", responses: [new OA\Response(response: 201, description: "Referral created")])]
    public function store(Request $request): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $request->validate([
            'landing_url' => 'nullable|string|max:500',
        ]);

        if ($landingUrl = $request->input('landing_url')) {
            $parsed = parse_url($landingUrl);
            if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https']) || !isset($parsed['host'])) {
                return response()->json(['error' => 'The landing URL must be a valid HTTP or HTTPS URL.'], 422);
            }
        }

        $referral = AgentReferral::create([
            'agent_id' => $agent->id,
            'referral_code' => strtoupper($agent->code . '-' . Str::random(6)),
            'landing_url' => $request->input('landing_url'),
        ]);

        return response()->json([
            'id' => $referral->id,
            'referral_code' => $referral->referral_code,
            'landing_url' => $referral->landing_url,
            'created_at' => $referral->created_at->toIso8601String(),
        ], 201);
    }

    #[OA\Get(path: "/agent/referrals/{id}", tags: ["Agent"], summary: "Get referral", responses: [new OA\Response(response: 200, description: "Referral returned")])]
    public function show(Request $request, string $id): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $referral = AgentReferral::where('agent_id', $agent->id)
            ->with('tenant:id,name,slug')
            ->findOrFail($id);

        return response()->json([
            'id' => $referral->id,
            'referral_code' => $referral->referral_code,
            'landing_url' => $referral->landing_url,
            'status' => $referral->converted_at ? 'converted' : ($referral->registered_at ? 'registered' : ($referral->clicked_at ? 'clicked' : 'created')),
            'tenant_name' => $referral->tenant?->name,
            'tenant_slug' => $referral->tenant?->slug,
            'commission_earned' => $referral->commission_earned,
            'clicked_at' => $referral->clicked_at?->toIso8601String(),
            'registered_at' => $referral->registered_at?->toIso8601String(),
            'trial_started_at' => $referral->trial_started_at?->toIso8601String(),
            'converted_at' => $referral->converted_at?->toIso8601String(),
            'first_payment_at' => $referral->first_payment_at?->toIso8601String(),
            'created_at' => $referral->created_at->toIso8601String(),
        ]);
    }

    #[OA\Get(path: "/agent/referrals/{id}/stats", tags: ["Agent"], summary: "Referral stats", responses: [new OA\Response(response: 200, description: "Stats returned")])]
    public function stats(Request $request, string $id): JsonResponse
    {
        $agent = $request->attributes->get('agent');

        $referral = AgentReferral::where('agent_id', $agent->id)->findOrFail($id);

        return response()->json([
            'clicks' => $referral->clicked_at ? 1 : 0,
            'registered' => $referral->registered_at ? 1 : 0,
            'trial_started' => $referral->trial_started_at ? 1 : 0,
            'converted' => $referral->converted_at ? 1 : 0,
            'first_payment' => $referral->first_payment_at ? 1 : 0,
            'commission_earned' => $referral->commission_earned,
        ]);
    }

    #[OA\Post(path: "/agent/referrals/track", tags: ["Agent"], summary: "Track referral click", responses: [new OA\Response(response: 200, description: "Click tracked")])]
    public function trackClick(Request $request): JsonResponse
    {
        $request->validate([
            'referral_code' => 'required|string|max:50',
            'ip_address' => 'nullable|ip|max:45',
        ]);

        $referral = AgentReferral::where('referral_code', $request->input('referral_code'))->first();

        if (!$referral) {
            return response()->json(['error' => 'Invalid referral code'], 404);
        }

        $referral->update([
            'clicked_at' => $referral->clicked_at ?? now(),
            'ip_address' => $request->input('ip_address') ?? $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return response()->json(['success' => true, 'landing_url' => $referral->landing_url]);
    }
}
