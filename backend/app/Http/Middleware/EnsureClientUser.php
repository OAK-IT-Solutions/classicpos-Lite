<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClientUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!$user instanceof \App\Models\Landlord\ClientUser) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$user->is_active) {
            return response()->json(['error' => 'Account is deactivated'], 403);
        }

        return $next($request);
    }
}
