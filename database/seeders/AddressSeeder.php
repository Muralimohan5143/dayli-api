<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Address;
use App\Models\User;

class AddressSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::take(5)->get();

        foreach ($users as $user) {
            Address::create([
                'user_id' => $user->id,
                'label' => 'Home',
                'line1' => 'House No. 123',
                'line2' => 'Near Temple',
                'city' => 'Hyderabad',
                'state' => 'Telangana',
                'pincode' => '500084',
                'lat' => 17.4700 + rand(-50, 50) / 10000,
                'lng' => 78.3800 + rand(-50, 50) / 10000,
                'nagar' => fake()->randomElement(['Sindhu Estate', 'Malla Reddy Venture', 'Miyapur Heights']),
                'is_default' => true
            ]);
        }
    }
}
