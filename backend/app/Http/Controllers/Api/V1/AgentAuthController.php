<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Rules\ComplexPassword;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/agent/auth")]
class AgentAuthController extends Controller
{
    /**
     * Public agent registration — creates AgentUser + Agent profile, returns token.
     */
    #[OA\Post(path: "/agent/auth/register", tags: ["Agent Auth"], summary: "Register a new agent", responses: [new OA\Response(response: 201, description: "Agent registered with token")])]
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => ['required', 'string', new ComplexPassword],
            'phone' => 'nullable|string|max:20',
        ]);

        if (AgentUser::where('email', $validated['email'])->exists()) {
            return response()->json(['error' => 'This email is already registered.'], 422);
        }

        $agentUser = AgentUser::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        $agentCode = strtoupper('AG-' . Str::random(6));

        $agent = Agent::create([
            'user_id' => $agentUser->id,
            'code' => $agentCode,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'tier' => 'standard',
            'commission_rate' => 10.00,
            'is_active' => true,
            'activated_at' => now(),
        ]);

        $token = $agentUser->createToken('agent-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $agentUser->id,
                'name' => $agentUser->name,
                'email' => $agentUser->email,
            ],
            'agent' => [
                'id' => $agent->id,
                'code' => $agent->code,
                'name' => $agent->name,
                'tier' => $agent->tier,
                'commission_rate' => $agent->commission_rate,
            ],
        ], 201);
    }

    /**
     * Public agent login — returns token.
     */
    #[OA\Post(path: "/agent/auth/login", tags: ["Agent Auth"], summary: "Login as agent", responses: [new OA\Response(response: 200, description: "Agent token returned")])]
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower($validated['email']);
        $throttleKey = 'login:agent:' . $email;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'error' => 'Too many failed login attempts. Try again in ' . ceil($seconds / 60) . ' minutes.',
            ], 429)->header('Retry-After', $seconds);
        }

        $agentUser = AgentUser::where('email', $email)->first();

        if (!$agentUser || !Hash::check($validated['password'], $agentUser->password)) {
            RateLimiter::hit($throttleKey, 900);
            return response()->json([
                'error' => 'Invalid email or password.',
            ], 401);
        }

        if (!$agentUser->is_active) {
            return response()->json([
                'error' => 'Your account is inactive. Please contact support.',
            ], 403);
        }

        $agent = Agent::where('user_id', $agentUser->id)->first();

        if (!$agent) {
            return response()->json([
                'error' => 'No agent profile found. Please contact support.',
            ], 403);
        }

        RateLimiter::clear($throttleKey);
        $token = $agentUser->createToken('agent-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $agentUser->id,
                'name' => $agentUser->name,
                'email' => $agentUser->email,
            ],
            'agent' => [
                'id' => $agent->id,
                'code' => $agent->code,
                'name' => $agent->name,
                'tier' => $agent->tier,
                'commission_rate' => $agent->commission_rate,
            ],
        ]);
    }

    /**
     * Get current agent profile (authenticated).
     */
    #[OA\Get(path: "/agent/auth/me", tags: ["Agent Auth"], summary: "Get current agent profile", responses: [new OA\Response(response: 200, description: "Agent profile")])]
    public function me(Request $request): JsonResponse
    {
        $agentUser = $request->user();
        $agent = Agent::where('user_id', $agentUser->id)->first();

        return response()->json([
            'user' => [
                'id' => $agentUser->id,
                'name' => $agentUser->name,
                'email' => $agentUser->email,
            ],
            'agent' => $agent ? [
                'id' => $agent->id,
                'code' => $agent->code,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'tier' => $agent->tier,
                'commission_rate' => $agent->commission_rate,
                'is_active' => $agent->is_active,
                'total_referrals' => $agent->total_referrals,
                'converted_referrals' => $agent->converted_referrals,
                'total_earnings' => $agent->total_earnings,
                'pending_earnings' => $agent->pending_earnings,
                'paid_earnings' => $agent->paid_earnings,
                'conversion_rate' => $agent->conversion_rate,
            ] : null,
        ]);
    }

    /**
     * Get current agent profile (authenticated) — read-only view.
     */
    #[OA\Get(path: "/agent/auth/profile", tags: ["Agent Auth"], summary: "Get agent profile read-only view", responses: [new OA\Response(response: 200, description: "Agent profile")])]
    public function profile(Request $request): JsonResponse
    {
        $agentUser = $request->user();
        $agent = Agent::where('user_id', $agentUser->id)->first();

        if (!$agent) {
            return response()->json(['error' => 'Agent profile not found.'], 404);
        }

        return response()->json([
            'user' => [
                'id' => $agentUser->id,
                'name' => $agentUser->name,
                'email' => $agentUser->email,
                'created_at' => $agentUser->created_at,
            ],
            'agent' => [
                'id' => $agent->id,
                'code' => $agent->code,
                'name' => $agent->name,
                'email' => $agent->email,
                'phone' => $agent->phone,
                'tier' => $agent->tier,
                'commission_rate' => $agent->commission_rate,
                'is_active' => $agent->is_active,
                'total_referrals' => $agent->total_referrals,
                'converted_referrals' => $agent->converted_referrals,
                'total_earnings' => $agent->total_earnings,
                'pending_earnings' => $agent->pending_earnings,
                'paid_earnings' => $agent->paid_earnings,
                'created_at' => $agent->created_at,
            ],
        ]);
    }

    /**
     * Update current agent profile — name, email, phone.
     */
    #[OA\Put(path: "/agent/auth/profile", tags: ["Agent Auth"], summary: "Update agent profile", responses: [new OA\Response(response: 200, description: "Profile updated")])]
    public function updateProfile(Request $request): JsonResponse
    {
        $agentUser = $request->user();
        $agent = Agent::where('user_id', $agentUser->id)->first();

        if (!$agent) {
            return response()->json(['error' => 'Agent profile not found.'], 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'phone' => 'nullable|string|max:50',
        ]);

        // Check email uniqueness across both AgentUser and Agent
        if (!empty($data['email'])) {
            if (AgentUser::where('email', $data['email'])->where('id', '!=', $agentUser->id)->exists()) {
                return response()->json(['error' => 'The email has already been taken.'], 422);
            }
            if (Agent::where('email', $data['email'])->where('id', '!=', $agent->id)->exists()) {
                return response()->json(['error' => 'The email has already been taken.'], 422);
            }
        }

        // Update AgentUser (name, email)
        $agentUserData = array_filter(['name' => $data['name'] ?? null, 'email' => $data['email'] ?? null], fn ($v) => $v !== null);
        if ($agentUserData) {
            $agentUser->update($agentUserData);
        }

        // Update Agent (name, email, phone)
        $agentData = array_filter([
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ], fn ($v) => $v !== null);
        if ($agentData) {
            $agent->update($agentData);
        }

        return response()->json([
            'user' => [
                'id' => $agentUser->fresh()->id,
                'name' => $agentUser->fresh()->name,
                'email' => $agentUser->fresh()->email,
            ],
            'agent' => [
                'id' => $agent->fresh()->id,
                'name' => $agent->fresh()->name,
                'email' => $agent->fresh()->email,
                'phone' => $agent->fresh()->phone,
            ],
        ]);
    }

    /**
     * Change current agent password.
     */
    #[OA\Post(path: "/agent/auth/change-password", tags: ["Agent Auth"], summary: "Change agent password", responses: [new OA\Response(response: 200, description: "Password changed")])]
    public function changePassword(Request $request): JsonResponse
    {
        $agentUser = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', new ComplexPassword, 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $agentUser->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 422);
        }

        $agentUser->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    /**
     * Logout — revoke current token.
     */
    #[OA\Post(path: "/agent/auth/logout", tags: ["Agent Auth"], summary: "Logout and revoke token", responses: [new OA\Response(response: 200, description: "Logged out")])]
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
