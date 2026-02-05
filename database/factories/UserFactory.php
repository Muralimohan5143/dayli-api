<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition(): array
    {
        $data = [
            'password' => bcrypt('password'),
            'remember_token' => Str::random(10),

            'account_status' => 'active',
            'origin_system'  => 'dayli',

            'first_name'   => $this->faker->firstName(),
            'last_name'    => $this->faker->lastName(),
            'display_name' => $this->faker->name(),

            'phone' => '9' . $this->faker->numerify('#########'),
            'email' => $this->faker->unique()->safeEmail(),

            'verified_email' => 0,
            'tax_exempt'     => 0,

            'number_of_orders' => 0,
            'amount_spent'      => 0,
            'total_amount_due'  => 0,

            'created_at' => now(),
            'updated_at' => now(),
        ];

        // optional: only if column exists in some environments
        if (Schema::hasColumn('users', 'role_id')) {
            $data['role_id'] = 3;
        }

        return $data;
    }

    /**
     * Indicate that the model's email address should be unverified.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function unverified()
    {
        return $this->state(fn() => [
            'verified_email' => 0,
        ]);
    }
}
