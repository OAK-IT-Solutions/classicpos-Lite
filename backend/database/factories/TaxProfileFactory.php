<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TaxProfileFactory extends Factory
{
    protected $model = \App\Models\TaxProfile::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => $this->faker->unique()->word() . ' Tax',
            'rate' => $this->faker->randomFloat(2, 5, 20),
            'type' => $this->faker->randomElement(['exclusive', 'inclusive']),
            'is_default' => false,
            'is_active' => true,
            'description' => $this->faker->sentence(),
        ];
    }

    public function default(): static
    {
        return $this->state(fn(array $attrs) => ['is_default' => true]);
    }
}
