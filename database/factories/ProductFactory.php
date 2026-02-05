<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id'    => $this->faker->unique()->numberBetween(1000, 9999),
            'title'         => $this->faker->words(2, true),
            'vendor'        => 'Dayli',
            'product_type'  => 'daily-need',
            'handle'        => Str::slug($this->faker->words(2, true)),
            'tags'          => '["milk","daily"]',
            'status'        => 'active',
            'img_src'       => $this->faker->imageUrl(640, 480, 'products', true),
            'created_at'    => now(),
            'updated_at'    => now(),
        ];
    }
}
