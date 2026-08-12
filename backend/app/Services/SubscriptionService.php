<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SubscriptionService
{
    public function getCurrent(Branch $branch): ?Subscription
    {
        return $branch->subscription;
    }

    public function createSubscription(Branch $branch, array $data): Subscription
    {
        $planType = $data['plan_type'] ?? 'standard';
        $billingCycle = $data['billing_cycle'] ?? 'monthly';

        return Subscription::create([
            'id' => (string) Str::uuid(),
            'branch_id' => $branch->id,
            'plan_type' => $planType,
            'billing_cycle' => $billingCycle,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(30),
            'starts_at' => now(),
        ]);
    }

    public function isActive(Branch $branch): bool
    {
        $subscription = $branch->subscription;

        if (!$subscription) {
            return false;
        }

        if ($subscription->status === 'cancelled' || $subscription->status === 'expired') {
            return false;
        }

        if ($subscription->status === 'trial' && $subscription->trial_ends_at && $subscription->trial_ends_at->isPast()) {
            return false;
        }

        if ($subscription->ends_at && $subscription->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    public function changePlan(Subscription $subscription, string $newPlan): Subscription
    {
        $subscription->update([
            'plan_type' => $newPlan,
        ]);

        return $subscription->fresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'ends_at' => now()->addDays(30),
        ]);

        return $subscription->fresh();
    }

    public function renew(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => now()->addYear(),
            'cancelled_at' => null,
        ]);

        return $subscription->fresh();
    }

    public function getPlanLimit(Branch $branch, string $metric): int
    {
        $subscription = $branch->subscription;

        if (!$subscription) {
            return 0;
        }

        $planType = $subscription->plan_type;
        $plans = config("plans.{$planType}", []);

        return $plans[$metric] ?? PHP_INT_MAX;
    }

    public function canCreateBranch(Branch $branch): bool
    {
        if (!$this->isActive($branch)) {
            return false;
        }

        $maxBranches = $this->getPlanLimit($branch, 'max_branches');
        $currentCount = Branch::count();

        return $currentCount < $maxBranches;
    }

    public function canCreateUser(Branch $branch): bool
    {
        if (!$this->isActive($branch)) {
            return false;
        }

        Branch::where('id', $branch->id)->lockForUpdate()->first();

        $maxUsers = $this->getPlanLimit($branch, 'max_users_per_branch');
        $currentCount = $branch->users()->count();

        return $currentCount < $maxUsers;
    }

    public function canCreateDevice(Branch $branch): bool
    {
        if (!$this->isActive($branch)) {
            return false;
        }

        Branch::where('id', $branch->id)->lockForUpdate()->first();

        $maxDevices = $this->getPlanLimit($branch, 'max_devices_per_branch');
        $currentCount = $branch->devices()->count();

        return $currentCount < $maxDevices;
    }

    public function getAvailablePlans(): array
    {
        return config('plans', []);
    }
}
