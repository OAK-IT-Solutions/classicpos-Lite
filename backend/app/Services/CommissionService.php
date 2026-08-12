<?php

namespace App\Services;

use App\Models\Landlord\Agent;
use App\Models\Landlord\AgentCommission;
use App\Models\Landlord\AgentReferral;
use App\Models\Landlord\PaymentTransaction;
use App\Models\Landlord\PlatformSetting;
use App\Models\Landlord\Subscription;
use App\Models\Landlord\SubscriptionPlan;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * Process commission when a subscription payment succeeds.
     *
     * Called after Pesapal activates a subscription. Finds the referring agent,
     * calculates the commission, updates the pending commission record, and
     * marks the referral as converted.
     */
    public function processSubscriptionPayment(PaymentTransaction $transaction, Subscription $subscription): void
    {
        $tenantId = $transaction->tenant_id;

        // Find the referral for this tenant
        $referral = AgentReferral::where('tenant_id', $tenantId)
            ->whereNull('converted_at')
            ->first();

        if (!$referral) {
            return; // No referral — nothing to do
        }

        $agent = Agent::find($referral->agent_id);
        if (!$agent || !$agent->is_active) {
            return;
        }

        // Calculate commission amount
        $plan = SubscriptionPlan::find($subscription->plan_id);
        $paymentAmount = (float) $transaction->amount;
        $commissionRate = (float) $agent->commission_rate;

        // Commission = payment amount * agent rate / 100
        $commissionAmount = round($paymentAmount * $commissionRate / 100, 2);

        // Find the existing pending commission (created during registration with $0)
        $commission = AgentCommission::where('agent_id', $agent->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->where('amount', 0)
            ->first();

        if ($commission) {
            // Update the existing $0 commission with real amount
            $commission->update([
                'amount' => $commissionAmount,
                'rate' => $commissionRate,
                'subscription_id' => $subscription->id,
                'payment_transaction_id' => $transaction->id,
                'notes' => "Commission for {$plan?->name} subscription ({$transaction->amount} x {$commissionRate}%)",
            ]);
        } else {
            // Create a new commission record
            AgentCommission::create([
                'agent_id' => $agent->id,
                'tenant_id' => $tenantId,
                'subscription_id' => $subscription->id,
                'payment_transaction_id' => $transaction->id,
                'amount' => $commissionAmount,
                'rate' => $commissionRate,
                'type' => 'subscription_referral',
                'status' => 'pending',
                'notes' => "Commission for {$plan?->name} subscription ({$transaction->amount} x {$commissionRate}%)",
            ]);
        }

        // Update agent earnings
        $agent->increment('pending_earnings', $commissionAmount);

        // Update referral timestamps
        $referral->update([
            'converted_at' => $referral->converted_at ?? now(),
            'first_payment_at' => $referral->first_payment_at ?? now(),
            'commission_earned' => $commissionAmount,
        ]);

        // Update agent conversion stats
        $agent->increment('converted_referrals');

        Log::info('Commission processed', [
            'agent_id' => $agent->id,
            'tenant_id' => $tenantId,
            'amount' => $commissionAmount,
            'rate' => $commissionRate,
        ]);
    }
}
