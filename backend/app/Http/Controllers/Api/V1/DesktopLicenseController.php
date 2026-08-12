<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DesktopLicenseController extends Controller
{
    private const LICENSE_FILE = 'license.json';

    /**
     * POST /api/v1/desktop/license/verify
     * Verify a license key offline.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string',
            'business_name' => 'nullable|string',
            'device_id' => 'nullable|string',
        ]);

        $result = LicenseService::validate(
            $request->key,
            $request->business_name ?? '',
            $request->device_id ?? '*',
        );

        return response()->json($result, $result['valid'] ? 200 : 422);
    }

    /**
     * POST /api/v1/desktop/license/activate
     * Activate a license key — stores it locally.
     */
    public function activate(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string',
            'business_name' => 'required|string',
        ]);

        $result = LicenseService::validate(
            $request->key,
            $request->business_name,
            $request->device_id ?? '*',
        );

        if (!$result['valid']) {
            return response()->json([
                'activated' => false,
                'error' => $result['error'],
            ], 422);
        }

        // Store license locally
        $licenseData = [
            'key' => $request->key,
            'business_name' => $request->business_name,
            'activated_at' => now()->toIso8601String(),
            'expires_at' => $result['data']['expiry'] ?? null,
            'features' => $result['data']['features'] ?? ['full_pos'],
            'device_fingerprint' => LicenseService::getDeviceFingerprint(),
        ];

        Storage::disk('local')->put(self::LICENSE_FILE, json_encode($licenseData, JSON_PRETTY_PRINT));

        return response()->json([
            'activated' => true,
            'license' => $licenseData,
        ]);
    }

    /**
     * GET /api/v1/desktop/license/status
     * Check current license status.
     */
    public function status(): JsonResponse
    {
        if (!Storage::disk('local')->exists(self::LICENSE_FILE)) {
            return response()->json([
                'activated' => false,
                'license' => null,
            ]);
        }

        $data = json_decode(Storage::disk('local')->get(self::LICENSE_FILE), true);

        // Re-validate to check expiry
        $result = LicenseService::validate(
            $data['key'] ?? '',
            $data['business_name'] ?? '',
            $data['device_fingerprint'] ?? '*',
        );

        return response()->json([
            'activated' => $result['valid'],
            'license' => $data,
            'expires_at' => $data['expires_at'] ?? null,
            'features' => $data['features'] ?? [],
            'validation_error' => $result['error'],
        ]);
    }

    /**
     * POST /api/v1/desktop/license/deactivate
     * Remove license (for device transfer).
     */
    public function deactivate(): JsonResponse
    {
        Storage::disk('local')->delete(self::LICENSE_FILE);

        return response()->json([
            'deactivated' => true,
        ]);
    }

    /**
     * GET /api/v1/desktop/license/generate-demo
     * Generate a demo license key (for testing only).
     */
    public function generateDemo(Request $request): JsonResponse
    {
        $key = LicenseService::generate(
            businessName: $request->get('business_name', 'Demo Business'),
            deviceId: '*',
            expiryDate: now()->addYear()->toIso8601String(),
            features: ['full_pos', 'reports', 'multi_branch'],
        );

        return response()->json([
            'key' => $key,
            'business_name' => $request->get('business_name', 'Demo Business'),
            'expires_at' => now()->addYear()->toIso8601String(),
        ]);
    }
}
