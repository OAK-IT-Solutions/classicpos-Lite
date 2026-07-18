<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Support\Facades\Log;

class PromotionService
{
    public function validateAndApply(?string $promoCode, float $subtotal): array
    {
        if (empty($promoCode)) {
            return ['discount' => 0.0, 'applied' => false];
        }

        $promotion = Promotion::where('code', $promoCode)
            ->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', now()->format('Y-m-d'));
            })
            ->where(function ($q) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()->format('Y-m-d'));
            })
            ->first();

        if (!$promotion) {
            Log::warning('Invalid or expired promo code used', ['code' => $promoCode]);
            return ['discount' => 0.0, 'applied' => false, 'error' => 'Invalid or expired promo code.'];
        }

        if ($promotion->usage_limit && $promotion->used_count >= $promotion->usage_limit) {
            Log::warning('Promo code usage limit reached', ['code' => $promoCode]);
            return ['discount' => 0.0, 'applied' => false, 'error' => 'Promo code has reached its usage limit.'];
        }

        if ($subtotal < $promotion->min_order_amount) {
            return [
                'discount' => 0.0,
                'applied' => false,
                'error' => 'Minimum order amount of ' . $promotion->min_order_amount . ' required for this promo code.',
            ];
        }

        $discountAmount = 0;

        if ($promotion->type === 'percentage') {
            $discountAmount = $subtotal * ($promotion->value / 100);
            if ($promotion->max_discount_amount && $discountAmount > $promotion->max_discount_amount) {
                $discountAmount = $promotion->max_discount_amount;
            }
        } else {
            $discountAmount = min($promotion->value, $subtotal);
        }

        $promotion->increment('used_count');

        return [
            'discount' => round($discountAmount, 2),
            'applied' => true,
            'promotion' => $promotion,
        ];
    }
}
