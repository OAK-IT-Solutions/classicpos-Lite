<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/roles")]
class RoleController extends Controller
{
    #[OA\Get(path: "/roles", tags: ["Roles"], summary: "List all roles", responses: [new OA\Response(response: 200, description: "Role list")])]
    public function index(): JsonResponse
    {
        $roles = Role::with('permissions')->get();

        return response()->json([
            'data' => $roles->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'is_editable' => $role->is_editable,
                'permissions' => $role->permissions->map(fn ($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ]),
                'created_at' => $role->created_at?->toIso8601String(),
            ]),
        ]);
    }

    #[OA\Get(path: "/roles/{id}", tags: ["Roles"], summary: "Get role details", responses: [new OA\Response(response: 200, description: "Role data")])]
    public function show(string $id): JsonResponse
    {
        $role = Role::with('permissions')->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'is_editable' => $role->is_editable,
                'permissions' => $role->permissions->map(fn ($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ]),
            ],
        ]);
    }

    #[OA\Post(path: "/roles", tags: ["Roles"], summary: "Create a role", responses: [new OA\Response(response: 201, description: "Role created")])]
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name',
            'guard_name' => 'nullable|string|max:50',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'uuid|exists:permissions,id',
        ]);

        $role = Role::create([
            'id' => (string) Str::uuid(),
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
            'is_editable' => true,
        ]);

        if (!empty($validated['permission_ids'])) {
            $role->permissions()->sync($validated['permission_ids']);
        }

        $role->load('permissions');

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'is_editable' => $role->is_editable,
                'permissions' => $role->permissions->map(fn ($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ]),
            ],
        ], 201);
    }

    #[OA\Put(path: "/roles/{id}", tags: ["Roles"], summary: "Update a role", responses: [new OA\Response(response: 200, description: "Role updated")])]
    public function update(Request $request, string $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if (!$role->is_editable) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_ROLE_NOT_EDITABLE',
                    'message' => 'Default roles cannot be modified.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name,' . $id,
            'guard_name' => 'nullable|string|max:50',
        ]);

        $role->update([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? $role->guard_name,
        ]);

        $role->load('permissions');

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'is_editable' => $role->is_editable,
                'permissions' => $role->permissions->map(fn ($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ]),
            ],
        ]);
    }

    #[OA\Delete(path: "/roles/{id}", tags: ["Roles"], summary: "Delete a role", responses: [new OA\Response(response: 200, description: "Role deleted")])]
    public function destroy(string $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if (!$role->is_editable) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_ROLE_NOT_EDITABLE',
                    'message' => 'Default roles cannot be deleted.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 403);
        }

        DB::table('permission_role')->where('role_id', $id)->delete();
        DB::table('role_user')->where('role_id', $id)->delete();
        $role->delete();

        return response()->json(['message' => 'Role deleted successfully.']);
    }

    #[OA\Put(path: "/roles/{id}/permissions", tags: ["Roles"], summary: "Sync role permissions", responses: [new OA\Response(response: 200, description: "Permissions synced")])]
    public function syncPermissions(Request $request, string $id): JsonResponse
    {
        $role = Role::findOrFail($id);

        if (!$role->is_editable) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_ROLE_NOT_EDITABLE',
                    'message' => 'Default roles cannot have their permissions modified.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 403);
        }

        $validated = $request->validate([
            'permission_ids' => 'required|array',
            'permission_ids.*' => 'uuid|exists:permissions,id',
        ]);

        $role->permissions()->sync($validated['permission_ids']);

        $role->load('permissions');

        return response()->json([
            'data' => [
                'id' => $role->id,
                'name' => $role->name,
                'permissions' => $role->permissions->map(fn ($perm) => [
                    'id' => $perm->id,
                    'name' => $perm->name,
                ]),
            ],
            'message' => 'Permissions updated successfully.',
        ]);
    }

    #[OA\Get(path: "/roles/permissions", tags: ["Roles"], summary: "List all permissions", responses: [new OA\Response(response: 200, description: "Permission list")])]
    public function allPermissions(): JsonResponse
    {
        return response()->json(['data' => Permission::all()]);
    }
}
