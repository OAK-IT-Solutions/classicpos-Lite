<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Beverages',
            'Beer',
            'Wine & Spirits',
            'Snacks',
            'Tobacco',
            'Dairy',
            'Bakery',
            'Fresh Produce',
            'Meat & Seafood',
            'Frozen Foods',
            'Canned Goods',
            'Condiments & Sauces',
            'Spices & Seasonings',
            'Baking Supplies',
            'Breakfast Foods',
            'Household Essentials',
            'Personal Care',
            'Baby Care',
            'Pet Supplies',
            'Office Supplies',
            'Electronics Accessories',
            'Other',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
