<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class SeedDemoMilkSupplies extends Command
{
    protected $signature = 'dayli:seed-demo-milk {--date=} {--force}';
    protected $description = 'Flush SCR/DO/DOI/Orders and seed demo vendor supplies + 15 customer subs + generate orders';

    public function handle(): int
    {
        if (!$this->option('force')) {
            $this->error("Refusing to run without --force (this command deletes data).");
            $this->line("Run: php artisan dayli:seed-demo-milk --force");
            return 1;
        }

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        // -------------------------
        // CONFIG YOU MUST ADJUST
        // -------------------------
        $zoneId = 1;

        // IMPORTANT: set these to your real product/variant IDs.
        // If you don't have products table in this DB, just pick existing IDs.
        $P = [
            // Using Shopify product IDs as product_id
            // variant_id: if you don’t have variant ids in DB, keep it same as product_id (safe placeholder)
            'vijaya_gold_500'   => ['product_id' => 8383403720978, 'variant_id' => 8383403720978, 'unit' => 'pkt'],
            'vijaya_small_pkt'  => ['product_id' => 8425366782226, 'variant_id' => 8425366782226, 'unit' => 'pkt'], // Vijaya toned milk 500ml
            'arokya_500ml'      => ['product_id' => 8383403917586, 'variant_id' => 8383403917586, 'unit' => 'pkt'],
            'vijaya_curd'       => ['product_id' => 8421025218834, 'variant_id' => 8421025218834, 'unit' => 'pkt'],
            'hatsun_curd'       => ['product_id' => 8409961103634, 'variant_id' => 8409961103634, 'unit' => 'pkt'],
        ];

        DB::beginTransaction();
        try {
            // -------------------------
            // 1) FLUSH TABLES
            // -------------------------
            // Use delete (not truncate) to avoid FK issues if you have them.
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            DB::table('order_items')->delete();
            DB::table('orders')->delete();
            DB::table('draft_order_items')->delete();
            DB::table('draft_orders')->delete();
            DB::table('sub_change_requests')->delete();

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // -------------------------
            // 2) CREATE SUPPLIERS (VENDORS) + CUSTOMERS
            // -------------------------
            // Assumption: vendors/customers are in `users` table.
            // If you have separate vendor table, change this section.
            $vendor1Id = $this->createUser('Vendor Vijaya Supplier', 'vendor.vijaya@example.test', '9990000001');
            $vendor2Id = $this->createUser('Vendor Arokya/Hatsun Supplier', 'vendor.arokya@example.test', '9990000002');

            $customerIds = [];
            for ($i = 1; $i <= 15; $i++) {
                $customerIds[] = $this->createUser("Customer {$i}", "customer{$i}@example.test", "90000000" . str_pad((string)$i, 2, '0', STR_PAD_LEFT));
            }

            // -------------------------
            // 3) CREATE CUSTOMER SUBS (SCR + Draft Order + DOI)
            // Supply Targets:
            // Vendor1: 25 vijaya gold + 15 vijaya small + 10 vijaya curd
            // Vendor2: 5 arokya 500ml + 3 hatsun curd (2 daily, 1 alternate day)
            // -------------------------

            // Distribution plan across 15 customers:
            // Vijaya Gold 25: C1-C10 => qty 2 daily (20), C11-C15 => qty 1 daily (5)
            // Vijaya Small 15: C1-C15 => qty 1 daily (15)
            // Arokya 5: C1-C5 => qty 1 daily (5)
            // Vijaya Curd 10: C1-C10 => qty 1 daily (10)
            // Hatsun Curd 3: C11-C12 => qty 1 daily, C13 => qty 1 alternate_days

            foreach ($customerIds as $idx => $cid) {
                $customerNo = $idx + 1;

                // SCR
                $scrId = DB::table('sub_change_requests')->insertGetId([
                    'by_user_id'  => $cid,   // ✅ required (who requested the change)
                    'for_user_id' => $cid,   // ✅ target customer
                    'zone_id'     => $zoneId,
                    'status'      => 'approved',   // optional but usually exists in SCR tables
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
                // Draft Order
                $draftOrderId = DB::table('draft_orders')->insertGetId([
                    'change_request_id' => $scrId,
                    'customer_id'       => $cid,     // ✅ ADD
                    'zone_id'           => $zoneId,  // ✅ ADD
                    'status'            => 'active',
                    'start_date'        => $date,
                    'end_date'          => null,
                    'created_at'        => now(),
                    'updated_at'        => now(),
                ]);

                // --- Items ---
                // Vijaya Small (all 15) - Vendor1
                $this->addDOI($draftOrderId, $P['vijaya_small_pkt'], $vendor1Id, 'daily', 1, $date);

                // Vijaya Gold - Vendor1
                $goldQty = ($customerNo <= 10) ? 2 : 1;
                $this->addDOI($draftOrderId, $P['vijaya_gold_500'], $vendor1Id, 'daily', $goldQty, $date);

                // Arokya 500ml (C1-C5) - Vendor2
                if ($customerNo <= 5) {
                    $this->addDOI($draftOrderId, $P['arokya_500ml'], $vendor2Id, 'daily', 1, $date);
                }

                // Vijaya Curd (C1-C10) - Vendor1
                if ($customerNo <= 10) {
                    $this->addDOI($draftOrderId, $P['vijaya_curd'], $vendor1Id, 'daily', 1, $date);
                }

                // Hatsun curd (C11-C13) - Vendor2
                if ($customerNo === 11 || $customerNo === 12) {
                    $this->addDOI($draftOrderId, $P['hatsun_curd'], $vendor2Id, 'daily', 1, $date);
                }
                if ($customerNo === 13) {
                    $this->addDOI($draftOrderId, $P['hatsun_curd'], $vendor2Id, 'alternate_days', 1, $date);
                }
            }


            // -------------------------
            // 3.5) CREATE SUPPLIER (VENDOR) SUPPLY ORDERS (SCR + DraftOrder + DOI + Orders + OI)
            // -------------------------
            //
            // We will model vendor supply as party_type='supplier'.
            // For simplicity, we will:
            // - create one SCR+DraftOrder per vendor
            // - create DOI rows representing what they supply today
            // - create an order for today linked to that draft_order_id
            // - create order_items for supplied quantities
            //
            // NOTE: orders.customer_id is NOT NULL in your schema, so we set customer_id = vendor_id (self).
            //

            // ---- Vendor1 supply: 25 gold + 15 small + 10 vijaya curd ----
            $vendor1ScrId = DB::table('sub_change_requests')->insertGetId([
                'by_user_id'   => $vendor1Id,
                'for_user_id'  => $vendor1Id,
                'party_type'   => 'supplier',   // ✅ IMPORTANT
                'zone_id'      => $zoneId,
                'status'       => 'approved',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $vendor1DraftOrderId = DB::table('draft_orders')->insertGetId([
                'change_request_id' => $vendor1ScrId,
                'customer_id'       => $vendor1Id, // required NOT NULL column in orders; reuse vendor as customer
                'vendor_id'         => $vendor1Id,
                'zone_id'           => $zoneId,
                'cadence'           => 'daily',
                'status'            => 'active',
                'start_date'        => $date,
                'end_date'          => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            // DOI (supply template for today)
            $this->addDOI($vendor1DraftOrderId, $P['vijaya_gold_500'],  $vendor1Id, 'daily', 25, $date);
            $this->addDOI($vendor1DraftOrderId, $P['vijaya_small_pkt'], $vendor1Id, 'daily', 15, $date);
            $this->addDOI($vendor1DraftOrderId, $P['vijaya_curd'],      $vendor1Id, 'daily', 10, $date);

            // Create vendor1 supply order for today
            $vendor1OrderId = DB::table('orders')->insertGetId([
                'order_type'      => 'subscription',
                'customer_id'     => $vendor1Id,         // required
                'vendor_id'       => $vendor1Id,         // identifies supplier
                'draft_order_id'  => $vendor1DraftOrderId,
                'zone_id'         => $zoneId,
                'delivery_date'   => $date,
                'delivery_status' => 'pending',
                'status'          => 'pending',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            // Insert supply order items from that draft order
            $vendor1Rows = DB::table('draft_order_items')
                ->where('draft_order_id', $vendor1DraftOrderId)
                ->where('status', 'active')
                ->get();

            foreach ($vendor1Rows as $r) {
                if (!$this->isDueOnDate($r->frequency_type, $r->start_date, $date)) continue;

                $title = DB::table('variants')
                    ->where('variant_id', (string)$r->variant_id)
                    ->value('title') ?: ('Item ' . (string)$r->product_id);

                DB::table('order_items')->insert([
                    'order_id'   => $vendor1OrderId,
                    'product_id' => (string) $r->product_id,
                    'variant_id' => (string) $r->variant_id,
                    'title'      => (string) $title,
                    'quantity'   => (int) round((float) $r->qty), // keep int packs
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }


            // ---- Vendor2 supply: 5 arokya + 3 hatsun (2 daily + 1 alternate_days) ----
            $vendor2ScrId = DB::table('sub_change_requests')->insertGetId([
                'by_user_id'   => $vendor2Id,
                'for_user_id'  => $vendor2Id,
                'party_type'   => 'supplier',   // ✅ IMPORTANT
                'zone_id'      => $zoneId,
                'status'       => 'approved',
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            $vendor2DraftOrderId = DB::table('draft_orders')->insertGetId([
                'change_request_id' => $vendor2ScrId,
                'customer_id'       => $vendor2Id,
                'vendor_id'         => $vendor2Id,
                'zone_id'           => $zoneId,
                'cadence'           => 'daily',
                'status'            => 'active',
                'start_date'        => $date,
                'end_date'          => null,
                'created_at'        => now(),
                'updated_at'        => now(),
            ]);

            $this->addDOI($vendor2DraftOrderId, $P['arokya_500ml'], '' . $vendor2Id, 'daily', 5, $date); // vendor id cast safe
            // Put full supply qty directly as daily for demo
            $this->addDOI($vendor2DraftOrderId, $P['hatsun_curd'], $vendor2Id, 'daily', 3, $date);

            $vendor2OrderId = DB::table('orders')->insertGetId([
                'order_type'      => 'subscription',
                'customer_id'     => $vendor2Id,
                'vendor_id'       => $vendor2Id,
                'draft_order_id'  => $vendor2DraftOrderId,
                'zone_id'         => $zoneId,
                'delivery_date'   => $date,
                'delivery_status' => 'pending',
                'status'          => 'pending',
                'created_at'      => now(),
                'updated_at'      => now(),
            ]);

            $vendor2Rows = DB::table('draft_order_items')
                ->where('draft_order_id', $vendor2DraftOrderId)
                ->where('status', 'active')
                ->get();

            foreach ($vendor2Rows as $r) {
                if (!$this->isDueOnDate($r->frequency_type, $r->start_date, $date)) continue;

                $title = DB::table('variants')
                    ->where('variant_id', (string)$r->variant_id)
                    ->value('title') ?: ('Item ' . (string)$r->product_id);

                DB::table('order_items')->insert([
                    'order_id'   => $vendor2OrderId,
                    'product_id' => (string) $r->product_id,
                    'variant_id' => (string) $r->variant_id,
                    'title'      => (string) $title,
                    'quantity'   => (int) round((float) $r->qty),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // -------------------------
            // 4) GENERATE ORDERS + ORDER_ITEMS FOR $date
            // -------------------------
            foreach ($customerIds as $cid) {
                $exists = DB::table('orders')
                    ->where('customer_id', $cid)
                    ->whereDate('delivery_date', $date)
                    ->exists();

                if ($exists) continue;

                $customerDraftOrderId = DB::table('draft_orders')
                    ->where('customer_id', $cid)
                    ->where('status', 'active')
                    ->orderByDesc('id')
                    ->value('id');

                if (!$customerDraftOrderId) {
                    throw new \RuntimeException("No active draft_order found for customer_id={$cid}");
                }

                $orderId = DB::table('orders')->insertGetId([
                    'order_type'      => 'subscription',
                    'customer_id'     => $cid,
                    'draft_order_id' => $customerDraftOrderId,
                    'zone_id'         => $zoneId,
                    'delivery_date'   => $date,
                    'delivery_status' => 'pending',
                    'status'          => 'pending',
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]);

                // pull active draft items for this customer
                $rows = DB::table('draft_order_items as doi')
                    ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')

                    ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
                    ->where('scr.for_user_id', $cid)
                    ->where('do.status', 'active')
                    ->where('doi.status', 'active')
                    ->select([
                        'doi.product_id',
                        'doi.variant_id',
                        'doi.vendor_id',
                        'doi.frequency_type',
                        'doi.qty',
                        'doi.unit',
                        'doi.start_date',
                        'doi.end_date',
                    ])
                    ->get();

                foreach ($rows as $r) {
                    if (!$this->isDueOnDate($r->frequency_type, $r->start_date, $date)) {
                        continue;
                    }


                    $title = DB::table('variants')
                        ->where('variant_id', (string)$r->variant_id)
                        ->value('title');

                    if (!$title) {
                        $title = 'Item ' . (string)$r->product_id; // fallback
                    }

                    DB::table('order_items')->insert([
                        'order_id'    => $orderId,
                        'product_id'  => (string) $r->product_id,
                        'variant_id'  => (string) $r->variant_id,
                        'title'       => (string) $title,   // ✅ REQUIRED
                        'quantity'    => (float) $r->qty,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }

            DB::commit();

            $this->info("✅ Seeded demo data for date {$date}");
            $this->line("Vendors: {$vendor1Id}, {$vendor2Id}");
            $this->line("Customers: " . count($customerIds));
            $this->line("Check totals by vendor for {$date}:");
            $this->line("  SELECT vendor_id, product_id, SUM(quantity) qty FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.delivery_date='{$date}' GROUP BY vendor_id, product_id;");

            return 0;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error($e->getMessage());
            return 1;
        }
    }

    private function createUser(string $name, string $email, string $phone): int
    {
        // If your users table requires password, add it.
        return DB::table('users')->insertGetId([
            'name'       => $name,
            'email'      => $email,
            'phone'      => $phone,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function addDOI(
        int $draftOrderId,
        array $p,
        int $vendorId,
        string $freq,
        float $qty,
        string $startDate
    ): void {
        // ✅ fetch actual variant_id from variants table
        $variantId = DB::table('variants')
            ->where('product_id', (string)$p['product_id'])   // product_id stored as string/bigint sometimes
            ->orderBy('variant_id', 'asc')
            ->value('variant_id');

        if (!$variantId) {
            throw new \RuntimeException("No variant found in variants table for product_id={$p['product_id']}");
        }

        DB::table('draft_order_items')->insert([
            'draft_order_id' => $draftOrderId,
            'product_id'     => (string) $p['product_id'],
            'variant_id'     => (string) $variantId,          // ✅ real FK
            'vendor_id'      => $vendorId,
            'frequency_type' => $freq,
            'qty'            => $qty,
            'unit'           => $p['unit'] ?? 'pkt',
            'start_date'     => $startDate,
            'end_date'       => null,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    private function isDueOnDate(?string $frequencyType, ?string $startDate, string $targetDate): bool
    {
        $frequencyType = $frequencyType ?: 'daily';
        if ($frequencyType === 'daily') return true;

        if ($frequencyType === 'alternate_days') {
            $start = $startDate ? Carbon::parse($startDate) : Carbon::parse($targetDate);
            $t     = Carbon::parse($targetDate);
            $diff  = $start->diffInDays($t);
            // due on day 0,2,4,... from start
            return ($diff % 2) === 0;
        }

        // For other types you can extend later (weekdays, sat, sun, custom, etc.)
        return true;
    }
}
