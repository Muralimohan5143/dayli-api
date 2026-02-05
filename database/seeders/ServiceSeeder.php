<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            [
                'title'       => 'Building Painter Services',
                'service_type'=> 'workman',
                'handle'      => Str::slug('building-painter'),
                'description' => 'Professional building painter services for homes, offices, and commercial properties.',
                'category'    => 'Home Improvement',
                'tags'        => json_encode(['painting','home','workman']),
                'img_src'     => 'services/painter.jpg',
            ],
            [
                'title'       => 'Carpenter Service',
                'service_type'=> 'workman',
                'handle'      => Str::slug('carpenter'),
                'description' => 'Skilled carpenter services for furniture repair, custom builds, and fittings.',
                'category'    => 'Home Improvement',
                'tags'        => json_encode(['carpentry','wood','furniture']),
                'img_src'     => 'services/carpenter.jpg',
            ],
            [
                'title'       => 'Cleaning Services',
                'service_type'=> 'workman',
                'handle'      => Str::slug('cleaning'),
                'description' => 'Residential and commercial cleaning services, one-time or recurring.',
                'category'    => 'Housekeeping',
                'tags'        => json_encode(['cleaning','maid','workman']),
                'img_src'     => 'services/cleaning.jpg',
            ],
            [
                'title'       => 'Cooking Service',
                'service_type'=> 'workman',
                'handle'      => Str::slug('cooking'),
                'description' => 'Personal cooking and catering services for homes and events.',
                'category'    => 'Housekeeping',
                'tags'        => json_encode(['cooking','chef','food']),
                'img_src'     => 'services/cooking.jpg',
            ],
            [
                'title'       => 'Electrical Services',
                'service_type'=> 'workman',
                'handle'      => Str::slug('electrical'),
                'description' => 'Certified electricians for home and office wiring, repair, and installations.',
                'category'    => 'Home Improvement',
                'tags'        => json_encode(['electrician','wiring','repairs']),
                'img_src'     => 'services/electrical.jpg',
            ],
            [
                'title'       => 'Gardening Service',
                'service_type'=> 'workman',
                'handle'      => Str::slug('gardening'),
                'description' => 'Professional gardening, landscaping, and lawn maintenance services.',
                'category'    => 'Outdoor',
                'tags'        => json_encode(['gardening','plants','landscaping']),
                'img_src'     => 'services/gardening.jpg',
            ],
            [
                'title'       => 'Home Security Service',
                'service_type'=> 'workman',
                'handle'      => Str::slug('home-security'),
                'description' => 'Trained security staff for residential and commercial premises.',
                'category'    => 'Security',
                'tags'        => json_encode(['security','guard','safety']),
                'img_src'     => 'services/security.jpg',
            ],
            [
                'title'       => 'Plumbing Services',
                'service_type'=> 'workman',
                'handle'      => Str::slug('plumbing'),
                'description' => 'Experienced plumbers for leak repairs, pipe fitting, and bathroom installation.',
                'category'    => 'Home Improvement',
                'tags'        => json_encode(['plumbing','pipes','repairs']),
                'img_src'     => 'services/plumbing.jpg',
            ],
        ];

        foreach ($services as $service) {
            DB::table('services')->updateOrInsert(
                ['handle' => $service['handle']], // unique key
                array_merge($service, [
                    'requires_booking' => 1,
                    'is_active'        => 1,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ])
            );
        }
    }
}
