<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Zone;
use App\Models\SubscriptionType;
use App\Models\VendorZoneSubscr;

class VendorZoneSubscrSeeder extends Seeder
{
    public function run(): void
    {
        // --- Resolve the zone (prefer code, then name, then fallback id=1)
        $zone = Zone::where('code', 'zone_nandyal_checkpost')->first()
            ?: Zone::where('name', 'LIKE', '%Checkpost%')->first()
            ?: Zone::find(1);

        if (! $zone) {
            $this->command->warn('VendorZoneSubscrSeeder: No zone found; skipping.');
            return;
        }

        // --- Resolve Subscription Type: Milk / Dairy
        $milkDairy = SubscriptionType::where('slug', 'milk-dairy')->first()
            ?: SubscriptionType::where('name', 'Milk / Dairy')->first()
            ?: SubscriptionType::where('name', 'Milk')->first();

        if (! $milkDairy) {
            $this->command->warn('VendorZoneSubscrSeeder: No "Milk / Dairy" subscription type found; skipping.');
            return;
        }

        // --- Vendors to link (phone => name is for log readability only)
        $vendors = [
            ['phone' => '9440444281', 'name' => 'Vishnu',  'is_preferred' => true,  'lead_time_mins' => 20],
            ['phone' => '9440720578', 'name' => 'Bhaskar', 'is_preferred' => false, 'lead_time_mins' => 30],
            ['phone' => '9494281119', 'name' => 'Murali',  'is_preferred' => false, 'lead_time_mins' => 30],
        ];

        foreach ($vendors as $v) {
            $vendor = User::where('phone', $v['phone'])->first();

            if (! $vendor) {
                $this->command->warn("VendorZoneSubscrSeeder: Vendor not found for phone {$v['phone']} ({$v['name']}); skipping.");
                continue;
            }

            // Upsert vendor availability for Milk/Dairy in the resolved zone
            VendorZoneSubscr::updateOrCreate(
                [
                    'vendor_id'            => $vendor->id,
                    'zone_id'              => $zone->id,
                    'subscription_type_id' => $milkDairy->id,
                ],
                [
                    'status'         => 'active',
                    'is_preferred'   => (bool)($v['is_preferred'] ?? false),
                    'lead_time_mins' => (int)($v['lead_time_mins'] ?? 30),
                    'meta'           => ['seeded' => true, 'notes' => 'initial wiring'],
                ]
            );

            // Ensure vendor has the correct role (idempotent)
            if (method_exists($vendor, 'assignRole')) {
                // Prefer the specific role if present; fallback to generic 'vendor'
                try {
                    if (app(\Spatie\Permission\PermissionRegistrar::class)) {
                        if ($vendor->hasRole('vendor-milk')) {
                            // already assigned
                        } elseif (\Spatie\Permission\Models\Role::where('name', 'vendor-milk')->exists()) {
                            $vendor->assignRole('vendor-milk');
                        } elseif (\Spatie\Permission\Models\Role::where('name', 'vendor')->exists()) {
                            $vendor->assignRole('vendor');
                        }
                    }
                } catch (\Throwable $e) {
                    // Non-fatal if Spatie not installed in this run
                    $this->command->warn("Role assignment skipped for {$vendor->name}: {$e->getMessage()}");
                }
            }
        }

        $this->command->info('VendorZoneSubscrSeeder: Vendor-zone-subscription mappings upserted.');
    }
}
