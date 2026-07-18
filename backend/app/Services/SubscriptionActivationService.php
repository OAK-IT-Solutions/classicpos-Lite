<?php

namespace App\Services;

use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use App\Models\Landlord\Tenant;
use Illuminate\Support\Facades\Log;

class SubscriptionActivationService
{
    public function __construct(
        private ?CommissionService $commissionService = null,
    ) {}

    public function activateFromTransaction(PaymentTransaction $transaction): ?Subscription
    {
        $subscription = Subscription::where('tenant_id', $transaction->tenant_id)
            ->where('status', '!=', 'cancelled')
            ->latest()
            ->first();

        if (!$subscription) {
            Log::warning('No active/pending subscription found for activation', [
                'tenant_id' => $transaction->tenant_id,
                'transaction_id' => $transaction->id,
            ]);
            return null;
        }

        $plan = SubscriptionPlan::find($subscription->plan_id);

        $duration = $subscription->billing_cycle === 'yearly' ? '1 year' : '1 month';
        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->add($duration),
            'trial_ends_at' => null,
            'amount' => $transaction->amount,
        ]);

        Tenant::where('id', $transaction->tenant_id)->update(['status' => 'active']);

        if ($this->commissionService) {
            try {
                $this->commissionService->processSubscriptionPayment($transaction, $subscription);
            } catch (\Exception $e) {
                Log::error('Commission processing failed during activation', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Subscription activated', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $transaction->tenant_id,
            'amount' => $transaction->amount,
            'billing_cycle' => $subscription->billing_cycle,
        ]);

        return $subscription;
    }
}
