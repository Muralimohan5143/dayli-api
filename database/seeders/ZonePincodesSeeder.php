<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Zone;
use App\Models\ZonePincode;

class ZonePincodesSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch the zone by code
        $zone = Zone::where('code', 'zone_kurnool_checkpost')->firstOrFail();

        // List of pincodes for this zone
        $pincodes = [
            '518001',
            '518002',
        ];

        foreach ($pincodes as $pin) {
            ZonePincode::updateOrCreate(
                ['pin_code' => $pin],
                ['zone_id'  => $zone->id]
            );
        }
    }
}