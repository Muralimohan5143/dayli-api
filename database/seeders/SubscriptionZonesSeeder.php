<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Zone;
use App\Models\SubscriptionType;
use App\Models\SubscriptionZone;

class SubscriptionZonesSeeder extends Seeder
{
    public function run(): void
    {
        $zone = Zone::where('code','zone_kurnool_checkpost')->firstOrFail();

        $slugs = ['milk-dairy','vegetables','fruits','grocery','bakery','water','newspaper'];
        foreach ($slugs as $slug) {
            $type = SubscriptionType::where('slug',$slug)->first();
            if (!$type) continue;

            SubscriptionZone::updateOrCreate(
                ['zone_id' => $zone->id, 'subscription_type_id' => $type->id],
                [
                    'status'       => 'active',
                    'available_from'=> now()->toDateString(),
                    'available_to'  => null,
                    'is_default'    => $slug === 'milk-dairy' ? 1 : 0,
                ]
            );
        }
    }
}
