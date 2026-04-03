<?php

namespace Database\Seeders;

use App\Models\DeliveryFeeRule;
use Illuminate\Database\Seeder;

class DeliveryFeeRuleSeeder extends Seeder
{
    public function run(): void
    {
        $bulk = 'qty < 25 ? 2 * qty : 50 * floor(qty / 25)';

        $rows = [
            [
                'product_id'  => 8425394307346,
                'variant_id'  => 52148034765074,
                'customer_id' => null,
                'title'       => 'Arokya TM Small',
                'fee_formula' => 'qty',
                'priority'    => 10,
                'is_active'   => true,
            ],
            [
                'product_id'  => 8383403917586,
                'variant_id'  => 45490819596562,
                'customer_id' => null,
                'title'       => 'Arokya Gold (500 ml)',
                'fee_formula' => 'qty',
                'priority'    => 10,
                'is_active'   => true,
            ],
            [
                'product_id'  => 8409961103634,
                'variant_id'  => 45560024826130,
                'customer_id' => null,
                'title'       => 'Hatsun Curd Big (400 g)',
                'fee_formula' => 'qty',
                'priority'    => 10,
                'is_active'   => true,
            ],
            [
                'product_id'  => 10288980754706,
                'variant_id'  => 51886974927122,
                'customer_id' => null,
                'title'       => 'Hatsun Curd Small',
                'fee_formula' => 'qty',
                'priority'    => 10,
                'is_active'   => true,
            ],
            [
                'product_id'  => 8409961103634,
                'variant_id'  => 52149601829138,
                'customer_id' => null,
                'title'       => 'Hatsun Curd Small (110 g)',
                'fee_formula' => 'qty',
                'priority'    => 10,
                'is_active'   => true,
            ],
            [
                'product_id'  => 8421025218834,
                'variant_id'  => 51886976270610,
                'customer_id' => null,
                'title'       => 'Vijaya Curd Small',
                'fee_formula' => 'qty',
                'priority'    => 10,
                'is_active'   => true,
            ],
            [
                'product_id'  => 8425366782226,
                'variant_id'  => 52148028047634,
                'customer_id' => null,
                'title'       => 'Vijaya Toned Milk Small',
                'fee_formula' => 'qty',
                'priority'    => 10,
                'is_active'   => true,
            ],
            [
                'product_id'  => 8425366782226,
                'variant_id'  => 45623554146578,
                'customer_id' => null,
                'title'       => 'Vijaya Toned Milk (500 ml)',
                'fee_formula' => $bulk,
                'priority'    => 10,
                'is_active'   => true,
            ],
            [
                'product_id'  => 8383403720978,
                'variant_id'  => 52149488976146,
                'customer_id' => null,
                'title'       => 'Vijaya Gold Milk (500 ml)',
                'fee_formula' => $bulk,
                'priority'    => 10,
                'is_active'   => true,
            ],
            [
                'product_id'  => 8421025218834,
                'variant_id'  => 45608528314642,
                'customer_id' => null,
                'title'       => 'Vijaya Curd (500 ml)',
                'fee_formula' => $bulk,
                'priority'    => 10,
                'is_active'   => true,
            ],
        ];

        foreach ($rows as $row) {
            DeliveryFeeRule::updateOrCreate(
                [
                    'product_id'  => $row['product_id'],
                    'variant_id'  => $row['variant_id'],
                    'customer_id' => $row['customer_id'],
                ],
                $row
            );
        }
    }
}
