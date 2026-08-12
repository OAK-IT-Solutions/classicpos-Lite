<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class InventoryFactory extends Factory
{
    protected $model = \App\Models\Inventory::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'product_id' => \App\Models\Product::factory(),
            'warehouse_id' => \App\Models\Warehouse::factory(),
            'quantity' => $this->faker->numberBetween(10, 100),
        ];
    }
}
