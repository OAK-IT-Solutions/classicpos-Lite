<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Landlord\AdminUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Rules\ComplexPassword;
use OpenApi\Attributes as OA;

class AdminAuthController extends Controller
{
    #[OA\Get(path: "/admin/auth/profile", tags: ["Admin Auth"], summary: "Get admin profile", responses: [new OA\Response(response: 200, description: "Profile returned")])]
    public function profile(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            'is_active' => $admin->is_active,
            'last_login_at' => $admin->last_login_at,
            'created_at' => $admin->created_at,
        ]);
    }

    #[OA\Put(path: "/admin/auth/profile", tags: ["Admin Auth"], summary: "Update admin profile", responses: [new OA\Response(response: 200, description: "Profile updated")])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
        ]);

        if (!empty($data['email']) && AdminUser::where('email', $data['email'])->where('id', '!=', $admin->id)->exists()) {
            return response()->json(['error' => 'The email has already been taken.'], 422);
        }

        $admin->update($data);

        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
        ]);
    }

    #[OA\Post(path: "/admin/auth/change-password", tags: ["Admin Auth"], summary: "Change admin password", responses: [new OA\Response(response: 200, description: "Password changed")])]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var AdminUser $admin */
        $admin = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', new ComplexPassword, 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $admin->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 422);
        }

        $admin->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    #[OA\Post(path: "/admin/auth/login", tags: ["Admin Auth"], summary: "Admin login", responses: [new OA\Response(response: 200, description: "Login successful")])]
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower($data['email']);
        $throttleKey = 'login:admin:' . $email;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'error' => 'Too many failed login attempts. Try again in ' . ceil($seconds / 60) . ' minutes.',
            ], 429)->header('Retry-After', $seconds);
        }

        $admin = AdminUser::where('email', $email)->first();

        if (!$admin || !Hash::check($data['password'], $admin->password)) {
            RateLimiter::hit($throttleKey, 900);
            return response()->json(['error' => 'Invalid email or password'], 401);
        }

        if (!$admin->is_active) {
            return response()->json(['error' => 'Account is deactivated'], 403);
        }

        RateLimiter::clear($throttleKey);
        $admin->update(['last_login_at' => now()]);
        $admin->tokens()->delete();

        $token = $admin->createToken('admin-auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ]);
    }

    #[OA\Get(path: "/admin/auth/me", tags: ["Admin Auth"], summary: "Get current admin user", responses: [new OA\Response(response: 200, description: "Current user returned")])]
    public function me(Request $request): JsonResponse
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        // Resolve from Bearer token directly, NOT from session.
        // Session may hold a regular User (no 'role' column).
        $sanctumToken = \App\Models\PersonalAccessToken::findToken($token);
        if (!$sanctumToken || !$sanctumToken->tokenable) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        /** @var \App\Models\Landlord\AdminUser $admin */
        $admin = $sanctumToken->tokenable;

        return response()->json([
            'id' => $admin->id,
            'name' => $admin->name,
            'email' => $admin->email,
            'role' => $admin->role,
            'is_active' => $admin->is_active,
        ]);
    }

    #[OA\Post(path: "/admin/auth/logout", tags: ["Admin Auth"], summary: "Admin logout", responses: [new OA\Response(response: 200, description: "Logged out")])]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
