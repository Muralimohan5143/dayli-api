<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Infra / roles
        $this->call([
            PermissionsSeeder::class,
            RolesAndPermissionsSeeder::class,
        ]);

        // Core geo + types
        $this->call([
            ZoneSeeder::class,
            ZonePincodesSeeder::class,
            //SubscriptionTypesSeeder::class,
            SeedSubscriptionTypesAndSubTypes::class,
            SubscriptionZonesSeeder::class,
            ServiceSeeder::class,
        ]);

        // Users
        $this->call([
            NandyalCheckpostMilkVendorWorkmanSeeder::class,       // Vishnu, Bhaskar, Murali + Manjunath
            // MilkCustomersFromCsvSeeder::class,  // enable if you want CSV-driven customers
        ]);

        // Catalog
        $this->call([
            //ProductsAndVariantsSeeder::class,
            VendorZoneSubscrSeeder::class,         // link vendors to zone + Milk/Dairy
        ]);

        // Optional domain (empty/no-op safe):
        // $this->call([ ServicesSeeder::class, ServiceVariantsSeeder::class ]);
        // $this->call([ DraftOrdersSeeder::class, DraftOrderItemsSeeder::class ]);
    }
}
