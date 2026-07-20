<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Landlord\PaymentTransaction;
use App\Services\LicenseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Desktop License Controller — handles license purchase, generation, and delivery.
 *
 * Flow:
 * 1. User purchases license via Pesapal/PayPal
 * 2. Payment webhook confirms payment
 * 3. License key is generated and stored
 * 4. License key is emailed to user
 * 5. User enters key in desktop app activation wizard
 */
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
            'email' => 'required|email',
            'plan' => 'required|in:professional,enterprise',
            'payment_method' => 'required|in:pesapal,paypal',
        ]);

        $plan = $request->plan;
        $amount = $plan === 'professional' ? 29.00 : 79.00;

        // Create pending payment
        $payment = PaymentTransaction::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => null, // Desktop license, not tied to tenant
            'type' => 'license_purchase',
            'amount' => $amount,
            'currency' => 'USD',
            'gateway' => $request->payment_method,
            'status' => 'pending',
            'metadata' => json_encode([
                'business_name' => $request->business_name,
                'email' => $request->email,
                'plan' => $plan,
                'product' => 'classicpos_desktop',
            ]),
        ]);

        return response()->json([
            'payment_id' => $payment->id,
            'amount' => $amount,
            'currency' => 'USD',
            'payment_method' => $request->payment_method,
            'business_name' => $request->business_name,
            'email' => $request->email,
        ]);
    }

    /**
     * POST /api/v1/desktop/license/complete
     * Complete a license purchase after payment confirmation.
     * Called by payment webhook or frontend after successful payment.
     */
    public function complete(Request $request): JsonResponse
    {
        $request->validate([
            'payment_id' => 'required|string',
            'transaction_id' => 'nullable|string',
        ]);

        $payment = PaymentTransaction::findOrFail($request->payment_id);

        if ($payment->status === 'success') {
            return response()->json([
                'message' => 'License already generated',
                'license_key' => $payment->metadata['license_key'] ?? null,
            ]);
        }

        // Mark payment as success
        $payment->update([
            'status' => 'success',
            'gateway_ref' => $request->transaction_id,
            'paid_at' => now(),
        ]);

        $metadata = json_decode($payment->metadata, true);

        // Generate license key
        $expiryDate = match ($metadata['plan'] ?? 'professional') {
            'professional' => now()->addYear()->toIso8601String(),
            'enterprise' => null, // No expiry for enterprise
            default => now()->addYear()->toIso8601String(),
        };

        $features = match ($metadata['plan'] ?? 'professional') {
            'professional' => ['full_pos', 'reports', 'multi_branch'],
            'enterprise' => ['full_pos', 'reports', 'multi_branch', 'custom_integrations', 'priority_support'],
            default => ['full_pos'],
        };

        $licenseKey = LicenseService::generate(
            businessName: $metadata['business_name'] ?? 'Unknown',
            deviceId: '*',
            expiryDate: $expiryDate,
            features: $features,
        );

        // Store license key in payment metadata
        $payment->update([
            'metadata' => json_encode(array_merge($metadata, [
                'license_key' => $licenseKey,
                'generated_at' => now()->toIso8601String(),
            ])),
        ]);

        // Send license key via email
        try {
            Mail::to($metadata['email'])->send(
                new \App\Mail\DesktopLicenseMail(
                    businessName: $metadata['business_name'] ?? 'Unknown',
                    licenseKey: $licenseKey,
                    plan: $metadata['plan'] ?? 'professional',
                    expiresAt: $expiryDate,
                )
            );
        } catch (\Exception $e) {
            // Log but don't fail — key is still valid
            \Illuminate\Support\Facades\Log::error('Failed to send license email: ' . $e->getMessage());
        }

        return response()->json([
            'message' => 'License generated successfully',
            'license_key' => $licenseKey,
            'plan' => $metadata['plan'] ?? 'professional',
            'expires_at' => $expiryDate,
        ]);
    }

    /**
     * GET /api/v1/desktop/license/plans
     * List available license plans.
     */
    public function plans(): JsonResponse
    {
        return response()->json([
            'plans' => [
                'professional' => [
                    'name' => 'Professional',
                    'price' => 29.00,
                    'currency' => 'USD',
                    'billing' => 'one-time',
                    'features' => ['full_pos', 'reports', 'multi_branch'],
                    'description' => 'Full POS system with offline mode, reporting, and multi-branch support.',
                ],
                'enterprise' => [
                    'name' => 'Enterprise',
                    'price' => 79.00,
                    'currency' => 'USD',
                    'billing' => 'one-time',
                    'features' => ['full_pos', 'reports', 'multi_branch', 'custom_integrations', 'priority_support'],
                    'description' => 'Everything in Professional plus custom integrations and priority support.',
                ],
            ],
        ]);
    }
}
