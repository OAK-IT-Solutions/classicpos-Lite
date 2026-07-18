<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ProductFactory extends Factory
{
    protected $model = \App\Models\Product::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => $this->faker->unique()->word() . ' ' . $this->faker->word(),
            'barcode' => (string) $this->faker->unique()->randomNumber(8),
            'price' => $this->faker->randomFloat(2, 5, 100),
            'cost' => $this->faker->randomFloat(2, 2, 50),
            'stock_uom' => 'pcs',
            'min_stock' => 5,
            'is_active' => true,
        ];
    }
}
