<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\Zone;

class NandyalCheckpostMilkVendorWorkmanSeeder extends Seeder
{
    public function run(): void
    {
        $zone = Zone::where('code','zone_kurnool_checkpost')->firstOrFail();
        $roleVendorMilk = Role::firstOrCreate(['name' => 'vendor-milk']);
        $roleWorkman    = Role::firstOrCreate(['name' => 'workman']);

        $people = [
            ['name'=>'Vishnu',    'phone'=>'9440444281', 'role'=>'vendor-milk', 'nagar'=>'Checkpost'],
            ['name'=>'Bhaskar',   'phone'=>'9440720578', 'role'=>'vendor-milk', 'nagar'=>'Checkpost'],
            ['name'=>'Murali',    'phone'=>'9494281119', 'role'=>'vendor-milk', 'nagar'=>'Checkpost'],
            ['name'=>'Manjunath', 'phone'=>'6300845694', 'role'=>'workman',     'nagar'=>'Checkpost'],
        ];

        foreach ($people as $p) {
            $user = User::updateOrCreate(
                ['phone' => $p['phone']],
                [
                    'name'       => $p['name'],
                    'first_name' => $p['name'],
                    'last_name'  => null,
                    'email'      => null,
                    'zone_id'    => $zone->id,
                ]
            );

            $user->syncRoles([$p['role']]);

            // default address
            $exists = DB::table('addresses')->where([
                'addressable_type' => 'App\\Models\\User',
                'addressable_id'   => $user->id,
                'zone_id'          => $zone->id,
                'line1'            => 'Default Address',
                'nagar'            => $p['nagar'],
                'city'             => 'Kurnool',
                'state'            => 'Andhra Pradesh',
                'country'          => 'India',
                'pincode'          => '518002',
                'is_default'       => true,
            ])->first();

            $payload = [
                'addressable_type' => 'App\\Models\\User',
                'addressable_id'   => $user->id,
                'zone_id'          => $zone->id,
                'line1'            => 'Default Address',
                'line2'            => null,
                'nagar'            => $p['nagar'],
                'city'             => 'Kurnool',
                'state'            => 'Andhra Pradesh',
                'country'          => 'India',
                'pincode'          => '518002',
                'is_default'       => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            if ($exists) {
                DB::table('addresses')->where('id', $exists->id)->update($payload);
            } else {
                DB::table('addresses')->insert($payload);
            }
        }
    }
}
