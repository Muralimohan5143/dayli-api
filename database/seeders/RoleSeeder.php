<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'admin',
            'zones-head',
            'zones-director',
            'zone-manager',
            'vendor',
            'vendor-milk',
            'vendor-vegetable',
            'vendor-meat',
            'vendor-grocery',
            'workman',
            'workman-delivery-boy-milk',
            'workman-delivery-boy-grocery',
            'workman-delivery-boy-puja-items',
            'workman-washerman',
            'workman-plumber',
            'customer',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}


//<?php

// namespace Database\Seeders;

// use Illuminate\Database\Seeder;
// use Illuminate\Support\Facades\DB;

// class RoleSeeder extends Seeder
// {
//     /**
//      * Run the database seeds.
//      *
//      * @return void
//      */
//     public function run()
//     {
//         DB::table('roles')->insert([
//             'id' => 1,
//             'name' => 'Admin',
//             'description' => 'Admin user has full access',
//             'created_at' => now(),
//             'updated_at' => now()
//         ]);

//         DB::table('roles')->insert([
//             'id' => 2,
//             'name' => 'Creator',
//             'description' => 'Creator user can add new users',
//             'created_at' => now(),
//             'updated_at' => now()
//         ]);


//         DB::table('roles')->insert([
//             'id' => 3,
//             'name' => 'Member',
//             'description' => 'Member user has minimal access',
//             'created_at' => now(),
//             'updated_at' => now()
//         ]);
//     }
// }
