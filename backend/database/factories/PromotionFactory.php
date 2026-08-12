<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PromotionFactory extends Factory
{
    protected $model = \App\Models\Promotion::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'code' => strtoupper($this->faker->unique()->lexify('PROMO????')),
            'type' => $this->faker->randomElement(['percentage', 'flat']),
            'value' => $this->faker->randomFloat(2, 5, 50),
            'min_order_amount' => 0,
            'max_discount_amount' => null,
            'usage_limit' => null,
            'used_count' => 0,
            'valid_from' => now()->subMonth(),
            'valid_until' => now()->addYear(),
            'is_active' => true,
            'description' => $this->faker->sentence(),
        ];
    }
}
