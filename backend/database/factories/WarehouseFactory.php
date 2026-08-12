<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WarehouseFactory extends Factory
{
    protected $model = \App\Models\Warehouse::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'branch_id' => \App\Models\Branch::factory(),
            'name' => $this->faker->word() . ' Warehouse',
            'location' => $this->faker->city(),
            'is_active' => true,
        ];
    }
}
