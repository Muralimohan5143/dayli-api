<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SubscriptionType;
use Illuminate\Support\Facades\DB;

class MergeMilkDairySeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            // Find old IDs
            $milkId  = SubscriptionType::where('name', 'Milk')->value('id');
            $dairyId = SubscriptionType::where('name', 'Dairy')->value('id');

            // Create or get merged
            $merged = SubscriptionType::firstOrCreate(
                ['name' => 'Milk / Dairy'],
                ['status' => 'active']
            );

            // Reassign vendor_zone_subscr if needed
            if ($milkId || $dairyId) {
                DB::table('vendor_zone_subscr')
                    ->whereIn('subscription_id', [$milkId, $dairyId])
                    ->update(['subscription_id' => $merged->id]);
            }

            // Delete old rows
            SubscriptionType::whereIn('id', array_filter([$milkId, $dairyId]))->delete();

            $this->command->info("✅ Merged Milk + Dairy → Milk / Dairy");
        });
    }
}
