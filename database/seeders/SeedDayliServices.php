<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedDayliServices extends Seeder
{
    public function run(): void
    {
        $services = [
            'Household' => [
                ['Electrician', 'electrician', 'Repairs, fittings, wiring', [
                    ['Fan fitting', 300],
                    ['Wiring', 500],
                    ['Switch repair', 150],
                ]],
                ['Plumber', 'plumber', 'Leakage, taps, bathroom work', [
                    ['Leak repair', 300],
                    ['Tap fitting', 250],
                    ['Bathroom work', 700],
                ]],
                ['Carpenter', 'carpenter', 'Furniture repair and fittings', [
                    ['Furniture repair', 400],
                    ['Door fitting', 600],
                    ['Custom wood work', 1000],
                ]],
                ['AC Service', 'ac-service', 'AC repair and installation', [
                    ['AC general service', 600],
                    ['AC installation', 1500],
                    ['Gas filling check', 800],
                ]],
            ],

            'Beauty' => [
                ['Pedicure', 'pedicure', 'At-home women beauty service', [
                    ['Basic pedicure', 500],
                    ['Premium pedicure', 900],
                ]],
                ['Manicure', 'manicure', 'Nail care and grooming', [
                    ['Basic manicure', 400],
                    ['Premium manicure', 800],
                ]],
                ['Mehendi', 'mehendi', 'Functions, festivals, marriages', [
                    ['Simple mehendi', 500],
                    ['Bridal mehendi', 2500],
                ]],
                ['Bridal Makeup', 'bridal-makeup', 'Marriage and function makeup', [
                    ['Basic bridal makeup', 5000],
                    ['Premium bridal makeup', 10000],
                ]],
            ],

            'Medical' => [
                ['Nurse', 'nurse', 'Home nursing support', [
                    ['Nurse visit', 700],
                    ['Day care nurse', 1500],
                ]],
                ['Physiotherapist', 'physiotherapist', 'Physio visits at home', [
                    ['Physio visit', 800],
                    ['Weekly physio plan', 4000],
                ]],
                ['Caretaker', 'caretaker', 'Elderly and patient care', [
                    ['Day caretaker', 1200],
                    ['Night caretaker', 1500],
                ]],
            ],

            'Tutors' => [
                ['Home Tutor', 'home-tutor', 'School subject tuition', [
                    ['Primary classes', 3000],
                    ['High school classes', 5000],
                ]],
                ['Maths Tutor', 'maths-tutor', 'Maths coaching at home', [
                    ['Maths monthly tuition', 5000],
                ]],
            ],
        ];

        foreach ($services as $category => $items) {
            foreach ($items as $item) {
                [$title, $handle, $description, $variants] = $item;

                DB::table('services')->updateOrInsert(
                    ['handle' => $handle],
                    [
                        'title' => $title,
                        'service_type' => 'workman',
                        'handle' => $handle,
                        'description' => $description,
                        'category' => $category,
                        'tags' => json_encode([Str::slug($category), Str::slug($title)]),
                        'requires_booking' => 1,
                        'is_active' => 1,
                        'img_src' => null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );

                $service = DB::table('services')->where('handle', $handle)->first();

                foreach ($variants as $variant) {
                    DB::table('service_variants')->updateOrInsert(
                        [
                            'service_id' => $service->service_id,
                            'title' => $variant[0],
                        ],
                        [
                            'service_id' => $service->service_id,
                            'title' => $variant[0],
                            'sku' => strtoupper(Str::slug($handle . '-' . $variant[0], '-')),
                            'duration_minutes' => 60,
                            'currency' => 'INR',
                            'price' => $variant[1],
                            'taxable' => 1,
                            'updated_at' => now(),
                            'created_at' => now(),
                        ]
                    );
                }
            }
        }
    }
}
