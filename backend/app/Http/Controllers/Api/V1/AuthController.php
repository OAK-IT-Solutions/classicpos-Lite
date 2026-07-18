<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use App\Models\Branch;
use App\Models\Category;
use App\Models\Inventory;
use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\AgentReferral;
use App\Models\Landlord\Tenant;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\SendPasswordResetEmail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Rules\ComplexPassword;
use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    #[OA\Post(path: "/auth/login", tags: ["Auth"], summary: "Login with email & password", responses: [new OA\Response(response: 200, description: "Login successful")])]
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = strtolower($validated['email']);
        $throttleKey = 'login:' . $email;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return response()->json([
                'error' => [
                    'code' => 'ERR_TOO_MANY_ATTEMPTS',
                    'message' => 'Too many failed login attempts. Try again in ' . ceil($seconds / 60) . ' minutes.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 429)->header('Retry-After', $seconds);
        }

        $user = User::with('roles', 'branches')->where('email', $email)->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            RateLimiter::hit($throttleKey, 900);
            return response()->json([
                'error' => [
                    'code' => 'ERR_INVALID_CREDENTIALS',
                    'message' => 'The provided credentials are incorrect.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 401);
        }

        RateLimiter::clear($throttleKey);
        $user->load('roles.permissions', 'branch');
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        Auth::guard('web')->login($user);

        return response()->json([
            'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar_url' => $user->avatar_url,
                    'branch_id' => $user->branch_id,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                        'business_type' => $user->branch->business_type,
                        'location' => $user->branch->location,
                        'timezone' => $user->branch->timezone,
                    ] : null,
                    'assigned_branches' => $user->branches->map(fn ($b) => [
                        'id' => $b->id,
                        'name' => $b->name,
                        'location' => $b->location,
                    ]),
                    'roles' => $user->roles->pluck('name'),
                    'permissions' => $user->roles->flatMap(fn($role) => $role->permissions->pluck('name'))->unique()->values(),
                ],
        ]);
    }

    #[OA\Post(path: "/auth/register", tags: ["Auth"], summary: "Register a new user", responses: [new OA\Response(response: 201, description: "Registration successful")])]
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'string', new ComplexPassword],
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|in:bar_restaurant,retail,service,pharmacy',
            'location' => 'required|string|max:255',
            'timezone' => 'required|string|max:50',
            'currency' => 'required|string|size:3',
            'country' => 'nullable|string|size:2',
            'plan' => 'nullable|string|in:standard,premium',
            'billing_cycle' => 'nullable|string|in:monthly,annual',
            'referral_code' => 'nullable|string|max:32',
        ]);

        $response = DB::transaction(function () use ($validated) {
            $branch = Branch::create([
                'name' => $validated['business_name'],
                'location' => $validated['location'],
                'timezone' => $validated['timezone'],
                'business_type' => $validated['business_type'],
                'cloud_sync_status' => 'pending',
            ]);

            $warehouse = Warehouse::create([
                'branch_id' => $branch->id,
                'name' => $validated['business_name'] . ' Warehouse',
                'location' => $validated['location'],
                'is_active' => true,
            ]);

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'branch_id' => $branch->id,
                'is_active' => true,
                'is_protected' => true,
            ]);

            $adminRole = \App\Models\Role::firstOrCreate(
                ['name' => 'admin', 'guard_name' => 'web'],
                ['is_editable' => false],
            );

            $user->roles()->attach($adminRole->id, ['branch_id' => $branch->id]);
            $user->branches()->attach($branch->id);

            $this->seedDefaultProducts($branch->id, $warehouse->id, $validated['business_type']);

            BusinessProfile::create([
                'branch_id' => $branch->id,
                'legal_business_name' => $validated['business_name'],
                'business_type' => $validated['business_type'],
                'currency' => $validated['currency'],
                'country' => $validated['country'] ?? 'KE',
                'timezone' => $validated['timezone'],
                'location' => $validated['location'],
                'onboarding_completed' => true,
            ]);

            Subscription::create([
                'branch_id' => $branch->id,
                'plan_type' => $validated['plan'] ?? 'standard',
                'billing_cycle' => $validated['billing_cycle'] ?? 'monthly',
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(30),
                'starts_at' => now(),
            ]);

            $user->load('roles.permissions', 'branch', 'branches');
            $token = $user->createToken('auth-token')->plainTextToken;

            Auth::guard('web')->login($user);

            return [
                'branch' => $branch,
                'user' => $user,
                'token' => $token,
            ];
        });

        $this->handleReferral($validated['referral_code'] ?? null, $response['branch']);

        return response()->json([
            'token' => $response['token'],
            'user' => [
                'id' => $response['user']->id,
                'name' => $response['user']->name,
                'email' => $response['user']->email,
                'avatar_url' => $response['user']->avatar_url,
                'branch_id' => $response['user']->branch_id,
                'branch' => $response['branch'] ? [
                    'id' => $response['branch']->id,
                    'name' => $response['branch']->name,
                    'business_type' => $response['branch']->business_type,
                    'location' => $response['branch']->location,
                    'timezone' => $response['branch']->timezone,
                ] : null,
                'assigned_branches' => $response['user']->branches->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'location' => $b->location,
                ]),
                'roles' => $response['user']->roles->pluck('name'),
                'permissions' => $response['user']->roles->flatMap(fn($role) => $role->permissions->pluck('name'))->unique()->values(),
                'onboarding_completed' => true,
            ],
        ], 201);
    }

    private function handleReferral(?string $referralCode, $branch): void
    {
        if (!$referralCode) return;

        try {
            $referral = AgentReferral::where('referral_code', $referralCode)
                ->whereNull('converted_at')
                ->first();

            if (!$referral) return;

            $slug = strtolower(Str::slug($branch->name)) . '-' . substr($branch->id, 0, 8);

            $tenant = Tenant::firstOrCreate(
                ['slug' => $slug],
                [
                    'name' => $branch->name,
                    'status' => 'active',
                    'referred_by_agent_id' => $referral->agent_id,
                    'metadata' => ['registered_via' => 'referral', 'branch_id' => $branch->id],
                ]
            );

            $referral->update([
                'tenant_id' => $tenant->id,
                'registered_at' => $referral->registered_at ?? now(),
                'trial_started_at' => now(),
            ]);

            $referral->agent()->increment('total_referrals');

            AgentCommission::create([
                'agent_id' => $referral->agent_id,
                'tenant_id' => $tenant->id,
                'amount' => 0,
                'rate' => $referral->agent->commission_rate,
                'type' => 'subscription_referral',
                'status' => 'pending',
                'notes' => 'Auto-created on registration via referral code: ' . $referralCode,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to handle referral code: ' . $e->getMessage(), [
                'referral_code' => $referralCode,
                'branch_id' => $branch?->id,
            ]);
        }
    }

    private function seedDefaultProducts(string $branchId, string $warehouseId, string $businessType): void
    {
        if ($businessType === 'bar_restaurant') {
            $products = [
                ['name' => 'Tusker Lager 500ml', 'barcode' => 'BR001', 'category' => 'Beer', 'price' => 3.00, 'cost' => 1.80, 'stock_uom' => 'pcs', 'min_stock' => 50],
                ['name' => 'White Cap Lager 500ml', 'barcode' => 'BR002', 'category' => 'Beer', 'price' => 3.00, 'cost' => 1.80, 'stock_uom' => 'pcs', 'min_stock' => 50],
                ['name' => 'Guinness Draught 500ml', 'barcode' => 'BR003', 'category' => 'Beer', 'price' => 4.00, 'cost' => 2.50, 'stock_uom' => 'pcs', 'min_stock' => 30],
                ['name' => 'Smirnoff Vodka 750ml', 'barcode' => 'BR004', 'category' => 'Spirits', 'price' => 15.00, 'cost' => 9.00, 'stock_uom' => 'pcs', 'min_stock' => 10],
                ['name' => 'Johnnie Walker Red 750ml', 'barcode' => 'BR005', 'category' => 'Whisky', 'price' => 25.00, 'cost' => 16.00, 'stock_uom' => 'pcs', 'min_stock' => 5],
                ['name' => 'Coca Cola 330ml', 'barcode' => 'BR006', 'category' => 'Soft Drinks', 'price' => 1.50, 'cost' => 0.70, 'stock_uom' => 'pcs', 'min_stock' => 100],
                ['name' => 'Natural Mineral Water 500ml', 'barcode' => 'BR007', 'category' => 'Soft Drinks', 'price' => 1.00, 'cost' => 0.40, 'stock_uom' => 'pcs', 'min_stock' => 100],
                ['name' => 'Nyama Choma (1kg)', 'barcode' => 'BR008', 'category' => 'Food', 'price' => 12.00, 'cost' => 7.00, 'stock_uom' => 'kg', 'min_stock' => 10],
                ['name' => 'Chips/Fries Portion', 'barcode' => 'BR009', 'category' => 'Food', 'price' => 4.00, 'cost' => 1.50, 'stock_uom' => 'pcs', 'min_stock' => 40],
                ['name' => 'Samosa (Pc)', 'barcode' => 'BR010', 'category' => 'Snacks', 'price' => 1.00, 'cost' => 0.40, 'stock_uom' => 'pcs', 'min_stock' => 50],
                ['name' => 'Grilled Tilapia', 'barcode' => 'BR011', 'category' => 'Food', 'price' => 8.00, 'cost' => 4.50, 'stock_uom' => 'pcs', 'min_stock' => 10],
                ['name' => 'Fruit Juice 300ml', 'barcode' => 'BR012', 'category' => 'Soft Drinks', 'price' => 2.50, 'cost' => 1.20, 'stock_uom' => 'pcs', 'min_stock' => 30],
            ];
        } else {
            $products = [
                ['name' => 'Product 1', 'barcode' => 'GEN001', 'category' => 'General', 'price' => 10.00, 'cost' => 6.00, 'stock_uom' => 'pcs', 'min_stock' => 10],
                ['name' => 'Product 2', 'barcode' => 'GEN002', 'category' => 'General', 'price' => 20.00, 'cost' => 12.00, 'stock_uom' => 'pcs', 'min_stock' => 10],
            ];
        }

        $categoryCache = [];
        foreach ($products as $product) {
            $categoryName = $product['category'];
            if (!isset($categoryCache[$categoryName])) {
                $category = Category::firstOrCreate(['name' => $categoryName]);
                $categoryCache[$categoryName] = $category->id;
            }
        }

        foreach ($products as $product) {
            $record = Product::create([
                'name' => $product['name'],
                'barcode' => $product['barcode'] . '-' . substr($branchId, 0, 6),
                'category_id' => $categoryCache[$product['category']],
                'price' => $product['price'],
                'cost' => $product['cost'],
                'stock_uom' => $product['stock_uom'],
                'min_stock' => $product['min_stock'],
                'is_active' => true,
            ]);

            Inventory::create([
                'product_id' => $record->id,
                'warehouse_id' => $warehouseId,
                'quantity' => rand(20, 100),
                'batch_number' => 'BATCH-' . strtoupper(\Illuminate\Support\Str::random(6)),
                'expiry_date' => now()->addMonths(rand(3, 18)),
            ]);
        }
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function refresh(Request $request)
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $token = $user->createToken('auth-token')->plainTextToken;

        if ($user instanceof \App\Models\Landlord\AdminUser) {
            Auth::guard('web')->loginUsing($user->id, $user->getConnectionName());

            return response()->json([
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                ],
            ]);
        }

        $user->load('roles.permissions', 'branch.businessProfile', 'branches');
        $profile = $user->branch?->businessProfile;

        Auth::guard('web')->login($user);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'branch_id' => $user->branch_id,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'business_type' => $user->branch->business_type,
                    'location' => $user->branch->location,
                    'timezone' => $user->branch->timezone,
                ] : null,
                'assigned_branches' => $user->branches->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'location' => $b->location,
                ]),
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->roles->flatMap(fn($role) => $role->permissions->pluck('name'))->unique()->values(),
                'onboarding_completed' => $profile?->onboarding_completed ?? false,
            ],
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $status = Password::sendResetLink(
            $validated,
            function (User $user, string $token) {
                $user->notify(new SendPasswordResetEmail($token));
            }
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Password reset link sent to your email.'])
            : response()->json([
                'error' => [
                    'code' => 'ERR_RESET_FAILED',
                    'message' => 'Unable to send password reset link.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
    }

    public function resetPassword(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => ['required', 'string', new ComplexPassword, 'confirmed'],
        ]);

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password has been reset successfully.'])
            : response()->json([
                'error' => [
                    'code' => 'ERR_RESET_FAILED',
                    'message' => 'Invalid or expired reset token.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
    }

    public function sendEmailVerification(Request $request)
    {
        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Verification link sent.']);
    }

    public function verifyEmail(Request $request, string $id, string $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_INVALID_VERIFICATION',
                    'message' => 'Invalid verification link.',
                    'details' => [],
                    'timestamp' => now()->toIso8601String(),
                ],
            ], 400);
        }

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email already verified.']);
        }

        $user->markEmailAsVerified();

        return response()->json(['message' => 'Email verified successfully.']);
    }

    #[OA\Get(path: "/auth/me", tags: ["Auth"], summary: "Get authenticated user profile", responses: [new OA\Response(response: 200, description: "User profile")])]
    public function me(Request $request)
    {
        $user = $request->user()->load('branch.businessProfile', 'roles.permissions', 'branches');
        $profile = $user->branch?->businessProfile;

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'branch_id' => $user->branch_id,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'business_type' => $user->branch->business_type,
                    'location' => $user->branch->location,
                    'timezone' => $user->branch->timezone,
                ] : null,
                'assigned_branches' => $user->branches->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'location' => $b->location,
                ]),
                'roles' => $user->roles->pluck('name'),
                'permissions' => $user->roles->flatMap(fn($role) => $role->permissions->pluck('name'))->unique()->values(),
                'onboarding_completed' => $profile?->onboarding_completed ?? false,
            ],
        ]);
    }

    public function profile(Request $request)
    {
        $user = $request->user()->load('branch', 'roles.permissions', 'branches');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'branch_id' => $user->branch_id,
                'branch' => $user->branch ? [
                    'id' => $user->branch->id,
                    'name' => $user->branch->name,
                    'location' => $user->branch->location,
                ] : null,
                'assigned_branches' => $user->branches->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'location' => $b->location,
                ]),
                'roles' => $user->roles->map(fn($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'branch_id' => $role->pivot?->branch_id,
                ]),
                'permissions' => $user->roles->flatMap(fn($role) => $role->permissions->pluck('name'))->unique()->values(),
                'created_at' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'current_password' => 'required_with:password',
            'password' => ['nullable', 'string', new ComplexPassword],
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if (!empty($validated['password'])) {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return response()->json([
                    'error' => [
                        'code' => 'ERR_INVALID_PASSWORD',
                        'message' => 'Current password is incorrect.',
                        'details' => [],
                        'timestamp' => now()->toIso8601String(),
                    ],
                ], 400);
            }
            $validated['password'] = Hash::make($validated['password']);
        }
        unset($validated['current_password']);

        if ($request->hasFile('avatar')) {
            $path = $request->file('avatar')->store('avatars', 'public');
            $validated['avatar_url'] = '/storage/' . $path;
            if ($user->avatar_url) {
                $oldPath = public_path($user->avatar_url);
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }
        unset($validated['avatar']);

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully.',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
            ],
        ]);
    }

    public function getSecretQuestion(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => 'required|email|exists:users,email']);
        $user = User::where('email', $validated['email'])->first();

        if (!$user->secret_question) {
            return response()->json([
                'error' => ['code' => 'ERR_NO_SECRET', 'message' => 'No security question set for this account. Use email reset instead.'],
            ], 400);
        }

        return response()->json(['data' => ['question' => $user->secret_question]]);
    }

    public function verifySecret(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => 'required|email|exists:users,email',
            'answer' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user->secret_question || !$user->secret_answer) {
            return response()->json([
                'error' => ['code' => 'ERR_NO_SECRET', 'message' => 'No security question set for this account.'],
            ], 400);
        }

        if (!Hash::check($validated['answer'], $user->secret_answer)) {
            return response()->json([
                'error' => ['code' => 'ERR_WRONG_ANSWER', 'message' => 'The answer is incorrect.'],
            ], 403);
        }

        // Generate a reset token and store it (same table as email reset)
        $token = Str::random(60);
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['email' => $user->email, 'token' => Hash::make($token), 'created_at' => now()]
        );

        return response()->json(['data' => [
            'token' => $token,
            'email' => $user->email,
        ]]);
    }

    public function setSecretQuestion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'secret_question' => 'required|string|max:255',
            'secret_answer' => 'required|string|max:255',
        ]);

        $user = $request->user();
        $user->update([
            'secret_question' => $validated['secret_question'],
            'secret_answer' => Hash::make($validated['secret_answer']),
        ]);

        return response()->json(['message' => 'Security question saved successfully.']);
    }
}
