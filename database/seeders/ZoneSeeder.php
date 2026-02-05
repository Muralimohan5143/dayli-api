<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Zone;

class ZoneSeeder extends Seeder
{
    public function run(): void
    {
        Zone::updateOrCreate(
            ['code' => 'zone_kurnool_checkpost'],
            [
                'name'      => 'Kurnool Checkpost Zone',
                'nagars'    => 'Checkpost, Sree Rama Nagar, Maruti Nagar, Brindavan Nagar, Elkur Estate, Dhanalaxmi Nagar, Sudireddy Palli, Mallareddy Venture, SVS(Srivari Sudarshanam), JHB(Joharapuram Housing Board), Sindhu Estate, Ganesh Nagar',
                'focal_pt'  => 'Nandyal Checkpost',
                'focal_lat' => 15.828126,
                'focal_lon' => 78.036364,
                'status'    => 'active',
            ]
        );
    }
}
