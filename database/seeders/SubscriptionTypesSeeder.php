<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionType;
use Illuminate\Support\Str;

class SubscriptionTypesSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Milk / Dairy', 'slug' => 'milk-dairy', 'status' => 'active'],
            ['name' => 'Vegetables',   'slug' => 'vegetables', 'status' => 'active'],
            ['name' => 'Fruits',       'slug' => 'fruits',     'status' => 'active'],
            ['name' => 'Grocery',      'slug' => 'grocery',    'status' => 'active'],
            ['name' => 'Bakery',       'slug' => 'bakery',     'status' => 'active'],
            ['name' => 'Water',        'slug' => 'water',      'status' => 'active'],
            ['name' => 'Newspaper',    'slug' => 'newspaper',  'status' => 'active'],
            ['name' => 'Puja Samagri', 'slug' => 'puja-samagri','status' => 'active'],
            ['name' => 'Flowers',      'slug' => 'flowers',    'status' => 'active'],
            ['name' => 'Snacks',       'slug' => 'snacks',     'status' => 'active'],
            ['name' => 'Fish / Seafood','slug'=> 'fish-seafood','status' => 'active'],
            ['name' => 'Meat',         'slug' => 'meat',       'status' => 'active'],
            ['name' => 'Beverages',    'slug' => 'beverages',  'status' => 'active'],
            ['name' => 'Sweets',       'slug' => 'sweets',     'status' => 'active'],
        ];

        foreach ($types as $t) {
            SubscriptionType::updateOrCreate(
                ['slug' => $t['slug']],
                [
                    'name'   => $t['name'],
                    'status' => $t['status'],
                ]
            );
        }
    }
}
