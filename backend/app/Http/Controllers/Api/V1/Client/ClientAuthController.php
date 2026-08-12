<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Models\Landlord\ClientUser;
use App\Models\Landlord\EmailVerification;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ClientAuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:landlord.client_users,email',
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
            'company_name' => 'required|string|max:255',
            'company_phone' => 'required|string|max:50',
            'plan_id' => 'required|uuid|exists:landlord.subscription_plans,id',
        ]);

        $client = ClientUser::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'company_name' => $data['company_name'],
            'company_phone' => $data['company_phone'],
        ]);

        $plan = SubscriptionPlan::find($data['plan_id']);

        Subscription::create([
            'client_user_id' => $client->id,
            'plan_id' => $plan->id,
            'status' => 'trialing',
            'billing_cycle' => 'monthly',
            'amount' => $plan->price_monthly,
            'starts_at' => now(),
            'ends_at' => now()->addDays(14),
            'trial_ends_at' => now()->addDays(14),
        ]);

        return response()->json([
            'success' => true,
            'verification_required' => true,
            'email' => $client->email,
            'message' => 'Account created. Please check your email to verify your account.',
        ], 201);
    }

    public function generateVerificationToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
        ]);

        $client = ClientUser::where('email', strtolower($data['email']))->first();

        if (!$client) {
            return response()->json(['error' => 'No account found with this email'], 404);
        }

        if ($client->email_verified_at) {
            return response()->json(['error' => 'Email is already verified'], 400);
        }

        EmailVerification::where('client_user_id', $client->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $token = Str::random(64);

        EmailVerification::create([
            'client_user_id' => $client->id,
            'token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json([
            'success' => true,
            'token' => $token,
            'email' => $client->email,
            'expires_at' => now()->addHours(24)->toIso8601String(),
        ]);
    }

    public function verifyEmail(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => 'required|string|size:64',
        ]);

        $verification = EmailVerification::where('token', $data['token'])
            ->whereNull('used_at')
            ->first();

        if (!$verification) {
            return response()->json(['error' => 'Invalid or already used verification token'], 400);
        }

        if ($verification->isExpired()) {
            return response()->json(['error' => 'Verification token has expired. Please request a new one.'], 400);
        }

        $client = ClientUser::find($verification->client_user_id);
        if (!$client) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $client->update(['email_verified_at' => now()]);
        $verification->update(['used_at' => now()]);

        $client->tokens()->delete();
        $token = $client->createToken('client-auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'token' => $token,
            'user' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'company_name' => $client->company_name,
            ],
            'message' => 'Email verified successfully.',
        ]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower($data['email']);
        $throttleKey = 'login:client:' . $email;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'error' => 'Too many failed login attempts. Try again in ' . ceil($seconds / 60) . ' minutes.',
            ], 429)->header('Retry-After', $seconds);
        }

        $client = ClientUser::where('email', $email)->first();

        if (!$client || !Hash::check($data['password'], $client->password)) {
            RateLimiter::hit($throttleKey, 900);
            return response()->json(['error' => 'Invalid email or password'], 401);
        }

        if (!$client->is_active) {
            return response()->json(['error' => 'Account is deactivated'], 403);
        }

        if (!$client->email_verified_at) {
            return response()->json([
                'error' => 'Please verify your email before logging in.',
                'verification_required' => true,
                'email' => $client->email,
            ], 403);
        }

        RateLimiter::clear($throttleKey);
        $client->update(['last_login_at' => now()]);
        $client->tokens()->delete();

        $token = $client->createToken('client-auth-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'company_name' => $client->company_name,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $subscription = $client->subscriptions()
            ->with('plan')
            ->whereIn('status', ['active', 'trialing'])
            ->first();

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'company_name' => $client->company_name,
            'company_phone' => $client->company_phone,
            'email_verified_at' => $client->email_verified_at,
            'is_active' => $client->is_active,
            'last_login_at' => $client->last_login_at,
            'created_at' => $client->created_at,
            'subscription' => $subscription ? [
                'id' => $subscription->id,
                'plan' => [
                    'id' => $subscription->plan->id,
                    'name' => $subscription->plan->name,
                    'slug' => $subscription->plan->slug,
                ],
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'amount' => $subscription->amount,
                'starts_at' => $subscription->starts_at,
                'ends_at' => $subscription->ends_at,
                'trial_ends_at' => $subscription->trial_ends_at,
            ] : null,
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email',
            'company_name' => 'nullable|string|max:255',
            'company_phone' => 'nullable|string|max:50',
        ]);

        if (!empty($data['email']) && ClientUser::where('email', $data['email'])->where('id', '!=', $client->id)->exists()) {
            return response()->json(['error' => 'The email has already been taken.'], 422);
        }

        $client->update($data);

        return response()->json([
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'company_name' => $client->company_name,
            'company_phone' => $client->company_phone,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var ClientUser $client */
        $client = $request->user();

        $data = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        if (!Hash::check($data['current_password'], $client->password)) {
            return response()->json(['error' => 'Current password is incorrect.'], 422);
        }

        $client->update(['password' => Hash::make($data['password'])]);

        return response()->json(['message' => 'Password updated successfully.']);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();
        return response()->json(['message' => 'Logged out']);
    }
}
