<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Branch;
use App\Models\BusinessProfile;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DesktopSetupController extends Controller
{
    private function ensureMigrated(): void
    {
        if (!DB::getSchemaBuilder()->hasTable('users')) {
            $result = Artisan::call('migrate', ['--force' => true]);
            if ($result !== 0) {
                throw new \RuntimeException('Database migration failed: ' . Artisan::output());
            }
        }
    }

    public function check(): JsonResponse
    {
        try {
            $this->ensureMigrated();
            $hasUsers = User::exists();

            return response()->json([
                'setup_required' => !$hasUsers,
            ]);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DesktopSetup check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => [
                    'code' => 'ERR_SETUP_CHECK',
                    'message' => 'Setup check failed: ' . $e->getMessage(),
                ],
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            $this->ensureMigrated();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DesktopSetup migration failed', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => [
                    'code' => 'ERR_MIGRATION',
                    'message' => 'Database initialization failed: ' . $e->getMessage(),
                ],
            ], 500);
        }

        if (User::exists()) {
            return response()->json([
                'error' => [
                    'code' => 'ERR_SETUP_COMPLETE',
                    'message' => 'Setup has already been completed. Please log in.',
                ],
            ], 400);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'business_name' => 'required|string|max:255',
            'business_type' => 'required|string|in:bar_restaurant,retail,wholesale,salon,grocery,other',
            'currency' => 'required|string|size:3',
            'country' => 'required|string|max:100',
            'timezone' => 'required|string|max:50',
        ]);

        return DB::transaction(function () use ($validated) {
            // 1. Create roles if they don't exist
            foreach (['admin', 'cashier'] as $roleName) {
                DB::table('roles')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'is_editable' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 2. Create branch
            $branchId = (string) Str::uuid();
            DB::table('branches')->insert([
                'id' => $branchId,
                'name' => $validated['business_name'],
                'location' => $validated['country'],
                'timezone' => $validated['timezone'],
                'business_type' => $validated['business_type'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 3. Create warehouse for the branch
            DB::table('warehouses')->insert([
                'id' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'name' => $validated['business_name'] . ' Main Warehouse',
                'location' => $validated['country'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 4. Create business profile
            DB::table('business_profiles')->insert([
                'id' => (string) Str::uuid(),
                'branch_id' => $branchId,
                'legal_business_name' => $validated['business_name'],
                'trading_name' => $validated['business_name'],
                'business_type' => $validated['business_type'],
                'currency' => $validated['currency'],
                'country' => $validated['country'],
                'timezone' => $validated['timezone'],
                'onboarding_completed' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 5. Create admin user
            $userId = (string) Str::uuid();
            DB::table('users')->insert([
                'id' => $userId,
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'branch_id' => $branchId,
                'is_active' => true,
                'is_protected' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 6. Assign admin role
            $adminRoleId = DB::table('roles')->where('name', 'admin')->value('id');
            if ($adminRoleId) {
                DB::table('role_user')->insert([
                    'user_id' => $userId,
                    'role_id' => $adminRoleId,
                    'branch_id' => $branchId,
                ]);
            }

            // 7. Assign branch to user
            DB::table('branch_user')->insert([
                'user_id' => $userId,
                'branch_id' => $branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // 8. Create Sanctum token
            $tokenResult = User::find($userId)->createToken('desktop-setup-token');
            $token = $tokenResult->plainTextToken;

            $user = User::with('roles')->find($userId);

            return response()->json([
                'message' => 'Setup completed successfully.',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'branch_id' => $user->branch_id,
                    'roles' => $user->roles->pluck('name'),
                ],
            ]);
        });
    }
}
