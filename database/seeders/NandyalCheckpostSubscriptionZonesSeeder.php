<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Zone;

class NandyalCheckpostSubscriptionZonesSeeder extends Seeder
{
    public function run(): void
    {
        $zone = Zone::where('code', 'zone_nandyal_checkpost')->first();
        if (!$zone) {
            $this->command->warn('Zone "Nandyal Checkpost" not found. Run NandyalCheckpostZoneSeeder first.');
            return;
        }

        $types = DB::table('subscription_types')->pluck('id', 'name');

        foreach ($types as $name => $id) {
            DB::table('subscription_zones')->updateOrInsert(
                [
                    'zone_id' => $zone->id,
                    'subscription_type_id' => $id,
                ],
                [
                    'status' => 'active',
                    'is_default' => ($name === 'Milk'), // mark Milk as default
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Subscription zones seeded for Nandyal Checkpost.');
    }
}
