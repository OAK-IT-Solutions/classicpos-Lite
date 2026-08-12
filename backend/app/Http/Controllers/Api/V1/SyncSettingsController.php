<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BusinessProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

#[OA\PathItem(path: "/sync/settings")]
class SyncSettingsController extends Controller
{
    #[OA\Get(path: "/sync/settings", tags: ["Sync"], summary: "Get sync settings", responses: [new OA\Response(response: 200, description: "Sync settings")])]
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => ['message' => 'Unauthenticated']], 401);
        }

        $branchId = $user->branch_id ?? $user->branch?->id;
        if (!$branchId) {
            return response()->json([
                'data' => [
                    'sync_mode' => 'auto',
                    'auto_sync_interval_seconds' => 30,
                    'printer_config' => null,
                ],
            ]);
        }

        $profile = BusinessProfile::where('branch_id', $branchId)->first();
        $settings = $profile?->settings ?? [];

        return response()->json([
            'data' => [
                'sync_mode' => $settings['sync_mode'] ?? 'auto',
                'auto_sync_interval_seconds' => $settings['auto_sync_interval_seconds'] ?? 30,
                'printer_config' => $settings['printer_config'] ?? null,
            ],
        ]);
    }

    #[OA\Put(path: "/sync/settings", tags: ["Sync"], summary: "Update sync settings", responses: [new OA\Response(response: 200, description: "Updated settings")])]
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sync_mode' => 'required|string|in:auto,manual',
            'auto_sync_interval_seconds' => 'nullable|integer|min:10|max:600',
            'printer_config' => 'nullable|array',
            'printer_config.type' => 'nullable|string|in:usb,network,browser,disabled',
            'printer_config.ip_address' => 'nullable|string|max:45',
            'printer_config.port' => 'nullable|integer|min:1|max:65535',
            'printer_config.drawer_pin' => 'nullable|integer|in:2,5',
            'printer_config.printer_name' => 'nullable|string|max:255',
            'printer_config.device_id' => 'nullable|string|max:64',
        ]);

        $user = $request->user();
        $branchId = $user->branch_id ?? $user->branch?->id;

        if (!$branchId) {
            return response()->json(['error' => ['message' => 'No branch associated with user']], 422);
        }

        $profile = BusinessProfile::firstOrNew(['branch_id' => $branchId]);
        $existing = $profile->settings ?? [];
        $merged = array_merge($existing, $validated);

        // Ensure the business profile has required base fields
        if (!$profile->exists) {
            $profile->legal_business_name = $user->branch?->name ?? 'Business';
            $profile->branch_id = $branchId;
        }

        $profile->settings = $merged;
        $profile->save();

        return response()->json([
            'data' => [
                'sync_mode' => $merged['sync_mode'] ?? 'auto',
                'auto_sync_interval_seconds' => $merged['auto_sync_interval_seconds'] ?? 30,
                'printer_config' => $merged['printer_config'] ?? null,
            ],
        ]);
    }
}
