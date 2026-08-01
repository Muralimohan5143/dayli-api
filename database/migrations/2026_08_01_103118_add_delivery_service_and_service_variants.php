<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Add broad Delivery Service
        |--------------------------------------------------------------------------
        */

        DB::table('services')->updateOrInsert(
            [
                'handle' => 'delivery',
            ],
            [
                'title' => 'Delivery Service',
                'service_type' => 'common',
                'description' => 'Delivery services for milk, vegetables, fruits, groceries, medicines and other daily essentials.',
                'category' => 'Delivery',
                'tags' => json_encode([
                    'delivery',
                    'milk',
                    'vegetables',
                    'fruits',
                    'grocery',
                    'medicine',
                ]),
                'requires_booking' => false,
                'is_active' => true,
                'img_src' => 'services/delivery.jpg',
                'meta' => json_encode([
                    'supports_vendor' => true,
                    'supports_workman' => true,
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | 2. Resolve existing service IDs
        |--------------------------------------------------------------------------
        */

        $services = DB::table('services')
            ->whereIn('handle', [
                'building-painter',
                'carpenter',
                'cleaning',
                'cooking',
                'electrical',
                'gardening',
                'home-security',
                'plumbing',
                'delivery',
            ])
            ->pluck('service_id', 'handle');

        /*
        |--------------------------------------------------------------------------
        | 3. Service variants
        |--------------------------------------------------------------------------
        */

        $variants = [
            /*
            |--------------------------------------------------------------------------
            | Delivery variants
            |--------------------------------------------------------------------------
            */

            [
                'service_handle' => 'delivery',
                'title' => 'Milk Delivery',
                'sku' => 'SERVICE-DELIVERY-MILK',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => false,
                'meta' => [
                    'handle' => 'milk-delivery',
                    'subscription_type_slug' => 'milk',
                ],
            ],
            [
                'service_handle' => 'delivery',
                'title' => 'Vegetable Delivery',
                'sku' => 'SERVICE-DELIVERY-VEGETABLE',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => false,
                'meta' => [
                    'handle' => 'vegetable-delivery',
                    'subscription_type_slug' => 'vegetables',
                ],
            ],
            [
                'service_handle' => 'delivery',
                'title' => 'Fruit Delivery',
                'sku' => 'SERVICE-DELIVERY-FRUIT',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => false,
                'meta' => [
                    'handle' => 'fruit-delivery',
                    'subscription_type_slug' => 'fruits',
                ],
            ],
            [
                'service_handle' => 'delivery',
                'title' => 'Grocery Delivery',
                'sku' => 'SERVICE-DELIVERY-GROCERY',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => false,
                'meta' => [
                    'handle' => 'grocery-delivery',
                    'subscription_type_slug' => 'grocery',
                ],
            ],
            [
                'service_handle' => 'delivery',
                'title' => 'Medicine Delivery',
                'sku' => 'SERVICE-DELIVERY-MEDICINE',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => false,
                'meta' => [
                    'handle' => 'medicine-delivery',
                    'subscription_type_slug' => 'medicine',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Building painter variants
            |--------------------------------------------------------------------------
            */

            [
                'service_handle' => 'building-painter',
                'title' => 'Interior Painting',
                'sku' => 'SERVICE-PAINT-INTERIOR',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'interior-painting',
                ],
            ],
            [
                'service_handle' => 'building-painter',
                'title' => 'Exterior Painting',
                'sku' => 'SERVICE-PAINT-EXTERIOR',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'exterior-painting',
                ],
            ],
            [
                'service_handle' => 'building-painter',
                'title' => 'Texture Painting',
                'sku' => 'SERVICE-PAINT-TEXTURE',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'texture-painting',
                ],
            ],
            [
                'service_handle' => 'building-painter',
                'title' => 'Commercial Painting',
                'sku' => 'SERVICE-PAINT-COMMERCIAL',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'commercial-painting',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Carpenter variants
            |--------------------------------------------------------------------------
            */

            [
                'service_handle' => 'carpenter',
                'title' => 'Furniture Repair',
                'sku' => 'SERVICE-CARPENTER-FURNITURE-REPAIR',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'furniture-repair',
                ],
            ],
            [
                'service_handle' => 'carpenter',
                'title' => 'Door and Window Work',
                'sku' => 'SERVICE-CARPENTER-DOOR-WINDOW',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'door-window-work',
                ],
            ],
            [
                'service_handle' => 'carpenter',
                'title' => 'Custom Furniture',
                'sku' => 'SERVICE-CARPENTER-CUSTOM-FURNITURE',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'custom-furniture',
                ],
            ],
            [
                'service_handle' => 'carpenter',
                'title' => 'Wood Polishing',
                'sku' => 'SERVICE-CARPENTER-WOOD-POLISHING',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'wood-polishing',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Cleaning variants
            |--------------------------------------------------------------------------
            */

            [
                'service_handle' => 'cleaning',
                'title' => 'Home Cleaning',
                'sku' => 'SERVICE-CLEANING-HOME',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'home-cleaning',
                ],
            ],
            [
                'service_handle' => 'cleaning',
                'title' => 'Bathroom Cleaning',
                'sku' => 'SERVICE-CLEANING-BATHROOM',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'bathroom-cleaning',
                ],
            ],
            [
                'service_handle' => 'cleaning',
                'title' => 'Kitchen Cleaning',
                'sku' => 'SERVICE-CLEANING-KITCHEN',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'kitchen-cleaning',
                ],
            ],
            [
                'service_handle' => 'cleaning',
                'title' => 'Office Cleaning',
                'sku' => 'SERVICE-CLEANING-OFFICE',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'office-cleaning',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Cooking variants
            |--------------------------------------------------------------------------
            */

            [
                'service_handle' => 'cooking',
                'title' => 'Home Cook',
                'sku' => 'SERVICE-COOKING-HOME',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'home-cook',
                ],
            ],
            [
                'service_handle' => 'cooking',
                'title' => 'Event Cooking',
                'sku' => 'SERVICE-COOKING-EVENT',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'event-cooking',
                ],
            ],
            [
                'service_handle' => 'cooking',
                'title' => 'Catering Assistance',
                'sku' => 'SERVICE-COOKING-CATERING',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'catering-assistance',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Electrical variants
            |--------------------------------------------------------------------------
            */

            [
                'service_handle' => 'electrical',
                'title' => 'Electrical Repair',
                'sku' => 'SERVICE-ELECTRICAL-REPAIR',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'electrical-repair',
                ],
            ],
            [
                'service_handle' => 'electrical',
                'title' => 'Home Wiring',
                'sku' => 'SERVICE-ELECTRICAL-HOME-WIRING',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'home-wiring',
                ],
            ],
            [
                'service_handle' => 'electrical',
                'title' => 'Fan and Light Installation',
                'sku' => 'SERVICE-ELECTRICAL-FAN-LIGHT',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'fan-light-installation',
                ],
            ],
            [
                'service_handle' => 'electrical',
                'title' => 'Appliance Installation',
                'sku' => 'SERVICE-ELECTRICAL-APPLIANCE',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'appliance-installation',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Gardening variants
            |--------------------------------------------------------------------------
            */

            [
                'service_handle' => 'gardening',
                'title' => 'Garden Maintenance',
                'sku' => 'SERVICE-GARDEN-MAINTENANCE',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'garden-maintenance',
                ],
            ],
            [
                'service_handle' => 'gardening',
                'title' => 'Planting Service',
                'sku' => 'SERVICE-GARDEN-PLANTING',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'planting-service',
                ],
            ],
            [
                'service_handle' => 'gardening',
                'title' => 'Lawn Maintenance',
                'sku' => 'SERVICE-GARDEN-LAWN',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'lawn-maintenance',
                ],
            ],
            [
                'service_handle' => 'gardening',
                'title' => 'Landscaping',
                'sku' => 'SERVICE-GARDEN-LANDSCAPING',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'landscaping',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Home security variants
            |--------------------------------------------------------------------------
            */

            [
                'service_handle' => 'home-security',
                'title' => 'Residential Security Guard',
                'sku' => 'SERVICE-SECURITY-RESIDENTIAL',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'residential-security',
                ],
            ],
            [
                'service_handle' => 'home-security',
                'title' => 'Commercial Security Guard',
                'sku' => 'SERVICE-SECURITY-COMMERCIAL',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'commercial-security',
                ],
            ],
            [
                'service_handle' => 'home-security',
                'title' => 'Night Security Guard',
                'sku' => 'SERVICE-SECURITY-NIGHT',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'night-security',
                ],
            ],

            /*
            |--------------------------------------------------------------------------
            | Plumbing variants
            |--------------------------------------------------------------------------
            */

            [
                'service_handle' => 'plumbing',
                'title' => 'Leak Repair',
                'sku' => 'SERVICE-PLUMBING-LEAK-REPAIR',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'leak-repair',
                ],
            ],
            [
                'service_handle' => 'plumbing',
                'title' => 'Pipe Fitting',
                'sku' => 'SERVICE-PLUMBING-PIPE-FITTING',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'pipe-fitting',
                ],
            ],
            [
                'service_handle' => 'plumbing',
                'title' => 'Bathroom Installation',
                'sku' => 'SERVICE-PLUMBING-BATHROOM',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'bathroom-installation',
                ],
            ],
            [
                'service_handle' => 'plumbing',
                'title' => 'Water Tank and Motor Service',
                'sku' => 'SERVICE-PLUMBING-TANK-MOTOR',
                'duration_minutes' => null,
                'price' => 0,
                'taxable' => true,
                'meta' => [
                    'handle' => 'water-tank-motor-service',
                ],
            ],
        ];

        foreach ($variants as $variant) {
            $serviceId = $services[$variant['service_handle']] ?? null;

            if (!$serviceId) {
                continue;
            }

            DB::table('service_variants')->updateOrInsert(
                [
                    'sku' => $variant['sku'],
                ],
                [
                    'service_id' => $serviceId,
                    'title' => $variant['title'],
                    'duration_minutes' => $variant['duration_minutes'],
                    'currency' => 'INR',
                    'price' => $variant['price'],
                    'compare_at_price' => null,
                    'taxable' => $variant['taxable'],
                    'max_parallel_jobs' => null,
                    'meta' => json_encode($variant['meta']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        $skus = [
            'SERVICE-DELIVERY-MILK',
            'SERVICE-DELIVERY-VEGETABLE',
            'SERVICE-DELIVERY-FRUIT',
            'SERVICE-DELIVERY-GROCERY',
            'SERVICE-DELIVERY-MEDICINE',

            'SERVICE-PAINT-INTERIOR',
            'SERVICE-PAINT-EXTERIOR',
            'SERVICE-PAINT-TEXTURE',
            'SERVICE-PAINT-COMMERCIAL',

            'SERVICE-CARPENTER-FURNITURE-REPAIR',
            'SERVICE-CARPENTER-DOOR-WINDOW',
            'SERVICE-CARPENTER-CUSTOM-FURNITURE',
            'SERVICE-CARPENTER-WOOD-POLISHING',

            'SERVICE-CLEANING-HOME',
            'SERVICE-CLEANING-BATHROOM',
            'SERVICE-CLEANING-KITCHEN',
            'SERVICE-CLEANING-OFFICE',

            'SERVICE-COOKING-HOME',
            'SERVICE-COOKING-EVENT',
            'SERVICE-COOKING-CATERING',

            'SERVICE-ELECTRICAL-REPAIR',
            'SERVICE-ELECTRICAL-HOME-WIRING',
            'SERVICE-ELECTRICAL-FAN-LIGHT',
            'SERVICE-ELECTRICAL-APPLIANCE',

            'SERVICE-GARDEN-MAINTENANCE',
            'SERVICE-GARDEN-PLANTING',
            'SERVICE-GARDEN-LAWN',
            'SERVICE-GARDEN-LANDSCAPING',

            'SERVICE-SECURITY-RESIDENTIAL',
            'SERVICE-SECURITY-COMMERCIAL',
            'SERVICE-SECURITY-NIGHT',

            'SERVICE-PLUMBING-LEAK-REPAIR',
            'SERVICE-PLUMBING-PIPE-FITTING',
            'SERVICE-PLUMBING-BATHROOM',
            'SERVICE-PLUMBING-TANK-MOTOR',
        ];

        DB::table('service_variants')
            ->whereIn('sku', $skus)
            ->delete();

        DB::table('services')
            ->where('handle', 'delivery')
            ->delete();
    }
};
