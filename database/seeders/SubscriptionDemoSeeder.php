<?php

namespace Database\Seeders;

use App\Models\{DraftOrder, DraftOrderItem, Order, Product, SubChangeRequest, User};
use Illuminate\Database\Seeder;

class SubscriptionDemoSeeder extends Seeder
{
    public function run(): void
    {
        // 1) Customer
        $customer = User::firstOrCreate(
            ['contact' => 'customer@example.com'],
            ['name' => 'Demo Customer', 'password' => bcrypt('secret')]
        );

        // 2) Products (stub if you already have)
        $milkGold = Product::firstOrCreate(['title' => 'Vijaya Gold Milk']);
        $milkTM   = Product::firstOrCreate(['title' => 'Vijaya Toned Milk']);
        $curd     = Product::firstOrCreate(['title' => 'Curd 500g']);

        // 3) Approved Change Request (open-ended)
        $cr = SubChangeRequest::create([
            'user_id'    => $customer->id,
            'status'     => 'approved',
            'start_date' => now()->toDateString(),
            'end_date'   => null,
        ]);

        // 4) Draft Order (template)
        $draft = DraftOrder::create([
            'change_request_id' => $cr->id,
            'customer_id'       => $customer->id,
            'cadence'           => 'daily',
            'start_date'        => now()->toDateString(),
            'end_date'          => null,
            'status'            => 'active',
            'timezone'          => 'Asia/Kolkata',
        ]);

        // 5) Draft Items (1 × Gold, 1 × TM, 1 × Curd)
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'product_id'     => $milkGold->id,
            'vendor_id'      => null,
            'qty'            => 1,
            'unit'           => 'packets',
            'price_snapshot' => 0,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'product_id'     => $milkTM->id,
            'vendor_id'      => null,
            'qty'            => 1,
            'unit'           => 'packets',
            'price_snapshot' => 0,
        ]);
        DraftOrderItem::create([
            'draft_order_id' => $draft->id,
            'product_id'     => $curd->id,
            'vendor_id'      => null,
            'qty'            => 1,
            'unit'           => 'pcs',
            'price_snapshot' => 0,
        ]);
    }
}
