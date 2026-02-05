<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Zone;
use App\Models\ZonePincode;

class NandyalCheckpostZoneSeeder extends Seeder
{
    public function run(): void
    {
        $payload = [
            'name'      => 'Nandyal Checkpost Zone',
            'code'      => 'zone_nandyal_checkpost',
            'nagars'    => 'Checkpost, Sree Rama Nagar, Maruti Nagar, Brindavan Nagar, Elkur Estate, Dhanalaxmi Nagar, Sudireddy Palli, Mallareddy Venture, SVS(Srivari Sudarshanam), JHB(Joharapuram Housing Board), Sindhu Estate, Ganesh Nagar',
            'focal_pt'  => 'Nandyal Checkpost',
            'focal_lat' => 15.828126,
            'focal_lon' => 78.036364,
            'status'    => 'active',
        ];

        // Normalize nagars (unique + alpha sort)
        $list = array_filter(array_map('trim', explode(',', $payload['nagars'])));
        $list = array_unique($list);
        natcasesort($list);
        $payload['nagars'] = implode(', ', $list);

        // Upsert Zone
        $zone = Zone::updateOrCreate(['code' => $payload['code']], $payload);

        // Ensure pincode mapping
        ZonePincode::updateOrInsert(
            ['pin_code' => '518002'],          // unique globally
            ['zone_id'  => $zone->id]          // reassign if zone changed
        );
    }
}
