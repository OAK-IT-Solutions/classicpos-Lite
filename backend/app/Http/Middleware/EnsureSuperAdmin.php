<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Ensure the authenticated user is a super admin.
     * Super admins are users with the 'super_admin' role in the landlord database.
     *
     * In self-hosted mode, the 'admin' role is treated as super_admin.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // AdminUser model has role column directly
        if ($user instanceof \App\Models\Landlord\AdminUser) {
            if (config('landlord.self_hosted')) {
                if (in_array($user->role, ['super_admin', 'admin'])) {
                    return $next($request);
                }
            } else {
                if ($user->role === 'super_admin') {
                    return $next($request);
                }
            }
        } else {
            // Standard User model with roles() relationship (self-hosted mode)
            if (config('landlord.self_hosted')) {
                if ($user->roles()->where('name', 'admin')->exists()) {
                    return $next($request);
                }
            } else {
                if ($user->roles()->where('name', 'super_admin')->exists()) {
                    return $next($request);
                }
            }
        }

        return response()->json([
            'error' => 'Forbidden',
            'message' => 'Super admin access required.',
        ], 403);
    }
}
