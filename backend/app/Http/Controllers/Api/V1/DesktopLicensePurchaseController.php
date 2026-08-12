<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DesktopLicense;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class DesktopLicensePurchaseController extends Controller
{
    /**
     * POST /api/v1/desktop/license/purchase
     * Initiate a license purchase.
     */
    public function purchase(Request $request): JsonResponse
    {
        $request->validate([
            'business_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'plan' => 'required|in:professional,enterprise',
            'payment_method' => 'required|in:pesapal,paypal',
        ]);

        $amount = $request->plan === 'professional' ? 150.00 : 150.00;

        // Create pending license
        $license = DesktopLicense::create([
            'id' => (string) Str::uuid(),
            'business_name' => $request->business_name,
            'email' => $request->email,
            'license_key' => strtoupper('CPPOS-' . strtoupper(substr(bin2hex(random_bytes(10)), 0, 16))),
            'plan' => $request->plan,
            'amount' => $amount,
            'currency' => 'USD',
            'payment_method' => $request->payment_method,
            'status' => DesktopLicense::STATUS_PENDING,
            'metadata' => json_encode([
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]),
        ]);

        return response()->json([
            'license_id' => $license->id,
            'amount' => $amount,
            'currency' => 'USD',
            'payment_method' => $request->payment_method,
            'business_name' => $request->business_name,
            'email' => $request->email,
        ]);
    }

    /**
     * POST /api/v1/desktop/license/complete
     * Complete a license purchase after payment.
     */
    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'license_id' => 'required|string',
            'transaction_id' => 'nullable|string',
        ]);

        $license = DesktopLicense::findOrFail($request->license_id);

        if ($license->status === DesktopLicense::STATUS_ACTIVE) {
            return response()->json([
                'message' => 'License already active',
                'license_key' => $license->license_key,
            ]);
        }

        // Mark as active
        $license->update([
            'status' => DesktopLicense::STATUS_ACTIVE,
            'payment_reference' => $request->transaction_id ?? 'manual_' . uniqid(),
            'activated_at' => now(),
            'expires_at' => match ($license->plan) {
                DesktopLicense::PLAN_ENTERPRISE => null,
                default => now()->addYear(),
            },
        ]);

        // Generate the actual license key using LicenseService
        $features = $license->getFeatures();
        $expiryDate = $license->expires_at ? $license->expires_at->toIso8601String() : null;

        $licenseKey = LicenseService::generate(
            businessName: $license->business_name,
            deviceId: '*',
            expiryDate: $expiryDate,
            features: $features,
        );

        // Update with the real license key
        $license->update(['license_key' => $licenseKey]);

        // Send email
        try {
            Mail::to($license->email)->send(
                new \App\Mail\DesktopLicenseMail(
                    businessName: $license->business_name,
                    licenseKey: $licenseKey,
                    plan: $license->plan,
                    expiresAt: $expiryDate,
                )
            );
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to send license email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'License activated successfully',
            'license_key' => $licenseKey,
            'plan' => $license->plan,
            'expires_at' => $expiryDate,
        ]);
    }

    /**
     * GET /api/v1/desktop/license/plans
     * List desktop license plans.
     */
    public function plans(): JsonResponse
    {
        return response()->json([
            'plans' => [
                [
                    'id' => 'professional',
                    'name' => 'Professional',
                    'price' => 150.00,
                    'currency' => 'USD',
                    'billing' => 'one-time',
                    'features' => [
                        'Full offline POS system',
                        'USB & serial receipt printing',
                        'Cash drawer control',
                        'Sales & inventory reports',
                        'Multi-branch (up to 5 locations)',
                        'Barcode scanning',
                        'Auto-updates (1 year)',
                        'Cloudflare Tunnel remote access',
                    ],
                    'best_for' => 'Small retail shops, bars, and restaurants with 1-5 locations that need reliable offline POS.',
                    'updates' => '1 year included',
                    'cta' => 'Buy Now — $150',
                ],
                [
                    'id' => 'enterprise',
                    'name' => 'Enterprise',
                    'price' => 150.00,
                    'currency' => 'USD',
                    'billing' => 'one-time',
                    'features' => [
                        'Everything in Professional',
                        'Custom integrations',
                        'Priority support',
                        'SLA guarantee',
                        'Lifetime updates',
                        'Unlimited branches',
                        'Unlimited devices',
                    ],
                    'best_for' => 'Enterprise chains and multi-location operations needing custom integrations and priority support.',
                    'updates' => 'Lifetime',
                    'cta' => 'Buy Now — $150',
                ],
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/desktop-licenses
     * Admin: List all desktop licenses.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $query = DesktopLicense::query();

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('business_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('license_key', 'like', "%{$search}%");
            });
        }

        $licenses = $query->orderByDesc('created_at')->paginate(20);

        return response()->json([
            'data' => $licenses->items(),
            'current_page' => $licenses->currentPage(),
            'last_page' => $licenses->lastPage(),
            'total' => $licenses->total(),
        ]);
    }

    /**
     * GET /api/v1/admin/desktop-licenses/stats
     * Admin: Revenue stats for desktop licenses.
     */
    public function adminStats(): JsonResponse
    {
        $totalRevenue = DesktopLicense::where('status', '!=', 'voided')->sum('amount');
        $totalLicenses = DesktopLicense::count();
        $activeLicenses = DesktopLicense::where('status', 'active')->count();
        $pendingLicenses = DesktopLicense::where('status', 'pending')->count();

        $byPlan = DesktopLicense::select('plan', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as revenue'))
            ->where('status', '!=', 'voided')
            ->groupBy('plan')
            ->get();

        $recentSales = DesktopLicense::where('status', 'active')
            ->orderByDesc('activated_at')
            ->limit(5)
            ->get(['business_name', 'email', 'plan', 'amount', 'activated_at']);

        return response()->json([
            'total_revenue' => (float) $totalRevenue,
            'total_licenses' => $totalLicenses,
            'active_licenses' => $activeLicenses,
            'pending_licenses' => $pendingLicenses,
            'by_plan' => $byPlan,
            'recent_sales' => $recentSales,
        ]);
    }
}
