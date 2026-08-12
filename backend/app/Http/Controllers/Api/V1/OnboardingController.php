<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/onboarding")]
class OnboardingController extends Controller
{
    #[OA\Get(path: "/onboarding/status", tags: ["Onboarding"], summary: "Get onboarding status", responses: [new OA\Response(response: 200, description: "Onboarding status")])]
    public function status(Request $request)
    {
        $profile = $request->user()->branch?->businessProfile;

        return response()->json([
            'onboarding_completed' => $profile?->onboarding_completed ?? false,
            'profile' => $profile ? [
                'id' => $profile->id,
                'legal_business_name' => $profile->legal_business_name,
                'trading_name' => $profile->trading_name,
                'business_type' => $profile->business_type,
                'logo_url' => $profile->logo_url,
                'currency' => $profile->currency,
                'country' => $profile->country,
                'timezone' => $profile->timezone,
                'onboarding_completed' => $profile->onboarding_completed,
            ] : null,
        ]);
    }

    #[OA\Post(path: "/onboarding/complete", tags: ["Onboarding"], summary: "Complete onboarding", responses: [new OA\Response(response: 200, description: "Onboarding completed")])]
    public function complete(Request $request)
    {
        $validated = $request->validate([
            'legal_business_name' => 'required|string|max:255',
            'trading_name' => 'nullable|string|max:255',
            'business_type' => 'required|string|in:bar_restaurant,retail,service,pharmacy',
            'tax_id' => 'nullable|string|max:50',
            'vat_registered' => 'boolean',
            'currency' => 'required|string|size:3',
            'country' => 'required|string|max:100',
            'timezone' => 'required|string|max:50',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'established_year' => 'nullable|integer|min:1900|max:2030',
            'description' => 'nullable|string|max:500',
        ]);

        $branch = $request->user()->branch;
        if (!$branch) {
            return response()->json([
                'error' => ['code' => 'ERR_NO_BRANCH', 'message' => 'No branch assigned to user.', 'details' => [], 'timestamp' => now()->toIso8601String()],
            ], 400);
        }

        $profile = $branch->businessProfile;

        DB::transaction(function () use ($validated, $branch, $profile) {
            if ($profile) {
                $profile->update([
                    ...$validated,
                    'onboarding_completed' => true,
                ]);
            } else {
                BusinessProfile::create([
                    'id' => (string) Str::uuid(),
                    'branch_id' => $branch->id,
                    ...$validated,
                    'onboarding_completed' => true,
                ]);
            }

            $branch->update([
                'name' => $validated['legal_business_name'],
                'business_type' => $validated['business_type'],
                'timezone' => $validated['timezone'],
            ]);
        });

        return response()->json([
            'message' => 'Business profile completed successfully.',
            'onboarding_completed' => true,
        ]);
    }

    #[OA\Put(path: "/onboarding/profile", tags: ["Onboarding"], summary: "Update business profile", responses: [new OA\Response(response: 200, description: "Profile updated")])]
    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'legal_business_name' => 'required|string|max:255',
            'trading_name' => 'nullable|string|max:255',
            'business_type' => 'required|string|in:bar_restaurant,retail,service,pharmacy',
            'tax_id' => 'nullable|string|max:50',
            'vat_registered' => 'boolean',
            'currency' => 'sometimes|required|string|size:3',
            'country' => 'required|string|max:100',
            'timezone' => 'sometimes|required|string|max:50',
            'address_line1' => 'nullable|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'state_province' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|string|max:255',
            'registration_number' => 'nullable|string|max:100',
            'established_year' => 'nullable|integer|min:1900|max:2030',
            'description' => 'nullable|string|max:500',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $branch = $request->user()->branch;
        if (!$branch) {
            return response()->json([
                'error' => ['code' => 'ERR_NO_BRANCH', 'message' => 'No branch assigned.', 'details' => [], 'timestamp' => now()->toIso8601String()],
            ], 400);
        }

        $profile = $branch->businessProfile;
        if (!$profile) {
            return response()->json([
                'error' => ['code' => 'ERR_NO_PROFILE', 'message' => 'Complete onboarding first.', 'details' => [], 'timestamp' => now()->toIso8601String()],
            ], 400);
        }

        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('logos', 'public');
            $validated['logo_url'] = '/storage/' . $path;
            if ($profile->logo_url) {
                $oldPath = public_path($profile->logo_url);
                if (file_exists($oldPath)) {
                    @unlink($oldPath);
                }
            }
        }
        unset($validated['logo']);

        DB::transaction(function () use ($validated, $branch, $profile) {
            $profile->update($validated);
            $branch->update([
                'name' => $validated['legal_business_name'],
                'business_type' => $validated['business_type'],
                'timezone' => $validated['timezone'] ?? $profile->timezone,
            ]);
        });

        $profile->refresh();

        return response()->json([
            'message' => 'Business profile updated.',
            'profile' => $profile,
        ]);
    }
}
