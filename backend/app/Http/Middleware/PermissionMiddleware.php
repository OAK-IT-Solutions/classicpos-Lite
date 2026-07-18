<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class PermissionMiddleware
{
    public function handle(Request $request, Closure $next, string ...$permissions): mixed
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_UNAUTHORIZED',
                    'message' => 'Authentication required.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 401);
        }

        $user->loadMissing('roles.permissions');

        $userPermissions = $user->roles->flatMap(fn($role) => $role->permissions->pluck('name'))->unique()->values();

        foreach ($permissions as $permission) {
            if ($userPermissions->contains($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'error' => [
                'code' => 'ERR_FORBIDDEN',
                'message' => 'You do not have the required permission to access this resource.',
                'details' => [
                    'required' => $permissions,
                    'your_permissions' => $userPermissions->values(),
                    'your_roles' => $user->roles->pluck('name')->values(),
                ],
                'timestamp' => now()->toIso8601String(),
            ],
        ], 403);
    }
}
