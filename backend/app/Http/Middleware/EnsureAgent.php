<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAgent
{
    /**
     * Ensure the authenticated user is an agent (has an active agent profile).
     * In self-hosted mode, agents don't exist — return 404.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // In self-hosted mode, only AgentUser (landlord-level) can be agents
        if (config('landlord.self_hosted') && !($user instanceof \App\Models\Landlord\AgentUser)) {
            return response()->json(['error' => 'Agent portal not available in self-hosted mode.'], 404);
        }

        $agent = $user->agent()->where('is_active', true)->first();

        if (!$agent) {
            return response()->json(['error' => 'No active agent profile found.'], 403);
        }

        $request->attributes->set('agent', $agent);

        return $next($request);
    }
}
