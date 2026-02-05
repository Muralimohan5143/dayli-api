<?php

namespace Database\Seeders;

use App\Models\{Product, Variant, SubChangeRequest, DraftOrder, DraftOrderItem, User};
use Illuminate\Database\Seeder;

class VariantDraftDemoSeeder extends Seeder
{
    public function run(): void
    {
        $customer = User::firstOrCreate(
            ['contact' => 'customer@example.com'],
            ['name' => 'Demo Customer', 'password' => bcrypt('secret')]
        );

        // Shopify-like IDs (use any unique bigints)
        $pid = 1111110001;
        $v1  = 2222220001; // 1L
        $v2  = 2222220002; // 500ml

        $p = Product::updateOrCreate(
            ['product_id' => $pid],
            ['title' => 'Vijaya Milk', 'vendor' => 'Vijaya', 'product_type' => 'milk', 'handle' => 'vijaya-milk-1l']
        );

        Variant::updateOrCreate(
            ['variant_id' => $v1],
            ['product_id' => $p->product_id, 'title' => '1L', 'price' => 62.00, 'currency' => 'INR', 'position' => 1]
        );
        Variant::updateOrCreate(
            ['variant_id' => $v2],
            ['product_id' => $p->product_id, 'title' => '500ml', 'price' => 34.00, 'currency' => 'INR', 'position' => 2]
        );

        // $cr = SubChangeRequest::updateOrCreate(
        //     ['for_user_id' => $customer->id, 'by_user_id' => $customer->id, 'start_date' => now()->toDateString()],
        //     ['status' => 'approved', 'end_date' => null]
        // );

        $cr = SubChangeRequest::create([
            'for_user_id' => $customer->id,
            'by_user_id'  => $customer->id,        // or admin user id
            'status'      => 'approved',
            'start_date'  => now(),                // DATETIME is fine
            'end_date'    => null,
        ]);


        $draft = DraftOrder::updateOrCreate(
            ['change_request_id' => $cr->id, 'customer_id' => $customer->id],
            ['cadence' => 'daily', 'start_date' => now()->toDateString(), 'status' => 'active', 'timezone' => 'Asia/Kolkata']
        );

        // Daily template: 1 × 1L + 1 × 500ml
        DraftOrderItem::updateOrCreate(
            ['draft_order_id' => $draft->id, 'variant_id' => $v1, 'vendor_id' => null],
            ['product_id' => $pid, 'qty' => 1, 'unit' => 'packets', 'price_snapshot' => null]
        );
        DraftOrderItem::updateOrCreate(
            ['draft_order_id' => $draft->id, 'variant_id' => $v2, 'vendor_id' => null],
            ['product_id' => $pid, 'qty' => 1, 'unit' => 'packets', 'price_snapshot' => null]
        );
    }
}
