<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BranchFactory extends Factory
{
    protected $model = \App\Models\Branch::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'name' => $this->faker->company() . ' Branch',
            'business_type' => 'bar_restaurant',
            'location' => $this->faker->city(),
            'timezone' => 'Africa/Nairobi',
        ];
    }
}
