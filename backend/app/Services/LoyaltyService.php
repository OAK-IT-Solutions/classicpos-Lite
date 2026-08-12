<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\LoyaltyRule;
use Illuminate\Support\Facades\Log;

class LoyaltyService
{
    public function awardPoints(string $customerId, float $amount): int
    {
        $rule = LoyaltyRule::where('is_active', true)->first();

        if (!$rule) {
            return 0;
        }

        $pointsAwarded = (int) floor($amount / $rule->points_per_amount) * $rule->points_earned;

        if ($pointsAwarded > 0) {
            Customer::where('id', $customerId)->increment('loyalty_points', $pointsAwarded);
            Log::info('Loyalty points awarded', [
                'customer_id' => $customerId,
                'points' => $pointsAwarded,
            ]);
        }

        return $pointsAwarded;
    }

    public function awardSignupBonus(string $customerId): int
    {
        $rule = LoyaltyRule::where('is_active', true)->first();

        if (!$rule || !$rule->signup_bonus_points) {
            return 0;
        }

        Customer::where('id', $customerId)->increment('loyalty_points', $rule->signup_bonus_points);

        Log::info('Signup bonus awarded', [
            'customer_id' => $customerId,
            'points' => $rule->signup_bonus_points,
        ]);

        return $rule->signup_bonus_points;
    }
}
