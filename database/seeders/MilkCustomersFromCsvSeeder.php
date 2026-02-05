<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use League\Csv\Reader;
use Illuminate\Support\Facades\DB;
use App\Models\Zone;
use App\Models\User;
use Spatie\Permission\Models\Role;

class MilkCustomersFromCsvSeeder extends Seeder
{
    public function run(): void
    {
        $zone = Zone::where('code','zone_kurnool_checkpost')->firstOrFail();
        $role = Role::firstOrCreate(['name' => 'customer']);

        $csvPath = base_path('database/seeders/data/milk_customers.csv'); // name,phone,line1,line2,nagar,city,state,country,pincode,email
        if (!file_exists($csvPath)) {
            $this->command->warn("CSV not found: {$csvPath} (skipping)");
            return;
        }

        $csv = Reader::createFromPath($csvPath, 'r');
        $csv->setHeaderOffset(0);

        foreach ($csv->getRecords() as $row) {
            $name  = trim($row['name'] ?? '');
            $phone = trim($row['phone'] ?? '') ?: null;

            if (!$name) continue;

            $user = User::updateOrCreate(
                $phone ? ['phone'=>$phone] : ['name'=>$name, 'zone_id'=>$zone->id],
                [
                    'name'       => $name,
                    'first_name' => strtok($name, ' '),
                    'last_name'  => trim(substr($name, strlen(strtok($name, ' '))) ?: ''),
                    'email'      => $row['email'] ?? null,
                    'zone_id'    => $zone->id,
                ]
            );

            $user->syncRoles(['customer']);

            $payload = [
                'addressable_type' => 'App\\Models\\User',
                'addressable_id'   => $user->id,
                'zone_id'          => $zone->id,
                'line1'            => $row['line1'] ?? null,
                'line2'            => $row['line2'] ?? null,
                'nagar'            => $row['nagar'] ?? null,
                'city'             => $row['city'] ?? 'Kurnool',
                'state'            => $row['state'] ?? 'Andhra Pradesh',
                'country'          => $row['country'] ?? 'India',
                'pincode'          => $row['pincode'] ?? '518002',
                'is_default'       => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ];

            $exists = DB::table('addresses')->where([
                'addressable_type' => 'App\\Models\\User',
                'addressable_id'   => $user->id,
                'is_default'       => true,
            ])->first();

            if ($exists) {
                DB::table('addresses')->where('id',$exists->id)->update($payload);
            } else {
                DB::table('addresses')->insert($payload);
            }
        }
    }
}
