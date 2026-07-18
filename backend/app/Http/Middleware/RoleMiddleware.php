<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): mixed
    {
        if (!$request->user()) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_FORBIDDEN',
                    'message' => 'You do not have the required role to access this resource.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 403);
        }

        foreach ($roles as $role) {
            if ($request->user()->roles()->where('name', $role)->exists()) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => [
                'code' => 'ERR_FORBIDDEN',
                'message' => 'You do not have the required role to access this resource.',
                'details' => [],
                'timestamp' => now()->toIso8601String(),
            ],
        ], 403);
    }
}
