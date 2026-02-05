<?php

namespace Database\Factories;

use App\Models\SubChangeRequest;
use App\Models\User;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubChangeRequestFactory extends Factory
{
    protected $model = SubChangeRequest::class;

    public function definition(): array
    {
        return [
            'for_user_id' => User::factory(),
            'by_user_id' => User::factory(),
            'from_id' => User::factory(),
            'product_id' => Product::factory(),
            'product_count' => $this->faker->numberBetween(1, 5),
            'status' => 'pending',

            'created_at' => now(),
            'start_date' => now(),
            'updated_at' => now(),
            'change_reason' => 'self_service',
            'frequency_type' => $this->faker->randomElement([
                'daily',
                'alternate_days',
                'weekdays',
                'weekends',
                'sat',
                'sun',
                'custom',
                'on_demand'
            ]),

        ];
    }
}
