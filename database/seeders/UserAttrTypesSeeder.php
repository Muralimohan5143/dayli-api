<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserAttrTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('user_attr_types')->insert([
            'name' => 'customer_dairy',
            'description' => 'Consumes dairy products',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'subscr_dairy',
            'description' => 'Subscribed to dairy products',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'vendor_dairy',
            'description' => 'Supplies dairy products',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'delivery_dairy',
            'description' => 'Delivers dairy products',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'customer_print_media',
            'description' => 'Consumes printed',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'subscr_print_media',
            'description' => 'Subscribed to printed media',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'vendor_print_media',
            'description' => 'Supplies printed media',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'delivery_print_media',
            'description' => 'Delivers printed media',
            'status' => 'active',
        ]);


        DB::table('user_attr_types')->insert([
            'name' => 'customer_veg',
            'description' => 'Consumes vegetables',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'subscr_veg',
            'description' => 'Subscribed to vegetables',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'vendor_veg',
            'description' => 'Supplies Vegetables',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'delivery_veg',
            'description' => 'Delivers vegetables',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'customer_fruit',
            'description' => 'Consumes fruits',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'subscr_fruit',
            'description' => 'Subscribed to fruits',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'vendor_fruit',
            'description' => 'Supplies Fruits',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'delivery_fruit',
            'description' => 'Delivers fruits',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'customer_meat',
            'description' => 'Consumes meats',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'subscr_meat',
            'description' => 'Subscribed to meats',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'vendor_meat',
            'description' => 'Supplies Meats',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'delivery_meat',
            'description' => 'Delivers meats',
            'status' => 'active',
        ]);


        DB::table('user_attr_types')->insert([
            'name' => 'customer_grocery',
            'description' => 'Consumes groceries',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'subscr_grocery',
            'description' => 'Subscribed to groceries',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'vendor_grocery',
            'description' => 'Supplies groceries',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'delivery_grocery',
            'description' => 'Delivers groceries',
            'status' => 'active',
        ]);

        // Services related data

        DB::table('user_attr_types')->insert([
            'name' => 'svcusr_ac_mechanic',
            'description' => 'Needs A/C Mechanic Service',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'svcpro_ac_mechanic',
            'description' => 'Provides A/C Mechanic Service',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'svcusr_plumber',
            'description' => 'Needs Plumbing Services',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'svcpro_plumber',
            'description' => 'Provides Plumbing Service',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'svcusr_electrician',
            'description' => 'Needs Plumbing Services',
            'status' => 'active',
        ]);

        DB::table('user_attr_types')->insert([
            'name' => 'svcpro_electrician',
            'description' => 'Provides Plumbing Service',
            'status' => 'active',
        ]);
    }
}
