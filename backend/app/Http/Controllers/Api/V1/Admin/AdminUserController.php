<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\AdminUser;
use App\Models\Landlord\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Rules\ComplexPassword;
use OpenApi\Attributes as OA;

class AdminUserController extends Controller
{
    #[OA\Get(path: "/admin/admin-users", tags: ["Admin Users"], summary: "List admin users", responses: [new OA\Response(response: 200, description: "Admin users listed")])]
    public function index(): JsonResponse
    {
        $users = AdminUser::orderByDesc('created_at')->get();
        return response()->json($users);
    }

    #[OA\Post(path: "/admin/admin-users", tags: ["Admin Users"], summary: "Create admin user", responses: [new OA\Response(response: 201, description: "Admin user created")])]
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => ['required', 'string', new ComplexPassword],
            'role' => 'required|in:super_admin,admin,support',
        ]);

        if (DB::connection('landlord')->table('admin_users')->where('email', $data['email'])->exists()) {
            return response()->json(['error' => 'The email has already been taken.'], 422);
        }

        $data['password'] = Hash::make($data['password']);

        $admin = AdminUser::create($data);

        AuditLog::log('admin_user.create', 'system', 'AdminUser', $admin->id, "Admin user {$admin->name} created");

        return response()->json($admin->makeVisible(['created_at']), 201);
    }

    #[OA\Get(path: "/admin/admin-users/{admin}", tags: ["Admin Users"], summary: "Get admin user", responses: [new OA\Response(response: 200, description: "Admin user details")])]
    public function show(AdminUser $admin): JsonResponse
    {
        return response()->json($admin->makeVisible(['last_login_at']));
    }

    #[OA\Put(path: "/admin/admin-users/{admin}", tags: ["Admin Users"], summary: "Update admin user", responses: [new OA\Response(response: 200, description: "Admin user updated")])]
    public function update(Request $request, AdminUser $admin): JsonResponse
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'password' => ['nullable', 'string', new ComplexPassword],
            'role' => 'sometimes|in:super_admin,admin,support',
            'is_active' => 'sometimes|boolean',
        ]);

        if (!empty($data['email']) && DB::connection('landlord')->table('admin_users')->where('email', $data['email'])->where('id', '!=', $admin->id)->exists()) {
            return response()->json(['error' => 'The email has already been taken.'], 422);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            $data['password'] = Hash::make($data['password']);
        }

        // Prevent self-deactivation
        if ($admin->id === $request->user()->id && isset($data['is_active']) && !$data['is_active']) {
            return response()->json(['error' => 'Cannot deactivate yourself'], 400);
        }

        $admin->update($data);

        AuditLog::log('admin_user.update', 'system', 'AdminUser', $admin->id, "Admin user {$admin->name} updated");

        return response()->json($admin->fresh());
    }

    #[OA\Delete(path: "/admin/admin-users/{admin}", tags: ["Admin Users"], summary: "Delete admin user", responses: [new OA\Response(response: 200, description: "Admin user deleted")])]
    public function destroy(AdminUser $admin): JsonResponse
    {
        if ($admin->id === request()->user()->id) {
            return response()->json(['error' => 'Cannot delete yourself'], 400);
        }

        AuditLog::log('admin_user.delete', 'system', 'AdminUser', $admin->id, "Admin user {$admin->name} deleted");
        $admin->delete();

        return response()->json(['message' => 'Admin user deleted']);
    }
}
