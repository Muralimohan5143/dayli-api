<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SubscrSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('subscr_types')->insert([
            'name' => 'dairy',
            'description' => 'Subscriptions to products like milk, curd, paneer, ghee etc.,',
            'status' => 'active',
        ]);

        DB::table('subscr_types')->insert([
            'name' => 'print_media',
            'description' => 'Subscriptions to products like newpaper, magazines and weeklies',
            'status' => 'active',
        ]);

        DB::table('subscr_types')->insert([
            'name' => 'vegetables',
            'description' => 'Subscriptions to vegetables on daily/weekly basis',
            'status' => 'planning',
        ]);   //

        DB::table('subscr_types')->insert([
            'name' => 'fruits',
            'description' => 'Subscriptions to fruits on daily/weekly basis',
            'status' => 'planning',
        ]);   //

        DB::table('subscr_types')->insert([
            'name' => 'meats',
            'description' => 'Subscriptions to meats on weekly basis',
            'status' => 'planning',
        ]);   //

        DB::table('subscr_types')->insert([
            'name' => 'groceries',
            'description' => 'Subscriptions to groceries on weekly basis',
            'status' => 'planning',
        ]);   //
    }
}
