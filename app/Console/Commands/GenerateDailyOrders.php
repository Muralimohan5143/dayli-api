<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GenerateDailyOrders extends Command
{
    // php artisan dayli:generate-daily-orders --date=2026-01-01   
    // php artisan dayli:generate-daily-orders --from=2026-01-01 --to=2026-04-02
    protected $signature = 'dayli:generate-daily-orders {--date=} {--from=} {--to=} {--customer_id=} {--dry}';
    protected $description = 'Create pending daily orders for active subscriptions';

    protected ?string $logFilePath = null;

    public function handle()
    {
        $isDryRun = (bool) $this->option('dry');
        $dates = $this->resolveDates();

        if (empty($dates)) {
            return self::FAILURE;
        }

        if ($isDryRun) {
            $timestamp = now()->format('Y-m-d-His');
            $this->logFilePath = storage_path("logs/daily-orders-dry-run-{$timestamp}.log");
            File::put($this->logFilePath, "DRY RUN - GenerateDailyOrders\n");
            File::append($this->logFilePath, "Generated at: " . now()->toDateTimeString() . "\n\n");
            $this->info("Dry run log file: {$this->logFilePath}");
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($dates as $date) {
            $this->line("Processing date: {$date}");

            $items = DB::table('draft_order_items as doi')
                ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
                ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
                ->leftJoin('users as u', 'u.id', '=', 'scr.for_user_id')
                ->leftJoin('products as p', 'p.product_id', '=', 'doi.product_id')
                ->leftJoin('variants as v', 'v.variant_id', '=', 'doi.variant_id')
                ->where('doi.status', 'active')
                ->whereNotNull('scr.for_user_id')
                ->whereDate('doi.start_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('doi.end_date')
                        ->orWhereDate('doi.end_date', '>=', $date);
                })
                ->where('doi.qty', '>', 0)
                ->when($this->option('customer_id'), function ($q, $customerId) {
                    $q->where('scr.for_user_id', (int) $customerId);
                })
                ->select([
                    'scr.for_user_id as actor_id',
                    'scr.party_type',
                    'u.display_name as actor_name',
                    'scr.zone_id as zone_id',
                    'do.id as draft_order_id',
                    'doi.id as doi_id',
                    'doi.product_id',
                    'doi.variant_id',
                    'doi.vendor_id',
                    'doi.qty',
                    'doi.unit',
                    'doi.price_snapshot',
                    'doi.frequency_type',
                    'doi.start_date',
                    'doi.end_date',
                    'doi.meta',

                    'p.title as product_title',
                    'p.vendor as brand',
                    'p.handle as product_handle',
                    'p.img_src as product_image',

                    'v.sku as sku',
                    'v.title as variant_title',
                ])
                ->orderBy('scr.for_user_id')
                ->orderBy('doi.id')
                ->get();

            $createdForDate = 0;
            $skippedForDate = 0;



            $grouped = [];

            foreach ($items as $item) {

                $currentDate = Carbon::parse($date);

                // ❗ safety: skip if no frequency
                if (!$item->frequency_type) {
                    continue;
                }

                // ✅ frequency filter
                if (!$this->matchesFrequency($item, $currentDate)) {
                    continue;
                }
                // 🔥 ADD THIS LINE HERE
                $this->writeLog("DEBUG | party_type={$item->party_type} | actor_id={$item->actor_id} | date={$date} | freq={$item->frequency_type} | start={$item->start_date}");

                // // 🔥 ADD HERE (exact place)
                // if ($item->frequency_type === 'alternate_days') {
                //     $start = Carbon::parse($item->start_date);

                //     if ($start->diffInDays($currentDate) < 0) {
                //         continue;
                //     }
                // }

                $key = $item->party_type . '_' . $item->actor_id . '_' . $date;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'party_type' => $item->party_type,
                        'actor_id' => $item->actor_id,
                        'actor_name' => $item->actor_name,
                        'zone_id' => $item->zone_id,
                        'draft_order_id' => $item->draft_order_id,
                        'items' => [],
                    ];
                }

                $grouped[$key]['items'][] = $item;
            }
            foreach ($grouped as $c) {

                $existingOrder = DB::table('orders')
                    ->when(
                        $c['party_type'] === 'supplier',
                        fn($q) => $q->where('vendor_id', $c['actor_id']),
                        fn($q) => $q->where('customer_id', $c['actor_id'])
                    )
                    ->whereDate('delivery_date', $date)
                    ->first();
                if ($existingOrder) {
                    $orderId = $existingOrder->id;
                    $skippedForDate++;
                    $totalSkipped++;

                    $this->writeLog("[SKIP ORDER CREATE] order already exists | party_type={$c['party_type']} | actor_id={$c['actor_id']} | actor_name={$c['actor_name']} | zone_id={$c['zone_id']} | date={$date}");
                } else {
                    if ($isDryRun) {
                        $createdForDate++;
                        $totalCreated++;

                        $message = "[DRY] would create order | party_type={$c['party_type']} | actor_id={$c['actor_id']} | actor_name={$c['actor_name']} | zone_id={$c['zone_id']} | draft_order_id={$c['draft_order_id']} | date={$date}";
                        $this->line($message);
                        $this->writeLog($message);

                        // simulate order items too
                        foreach ($c['items'] as $item) {
                            $message = "[DRY] would create order_item | party_type={$c['party_type']} | actor_id={$c['actor_id']} | doi_id={$item->doi_id} | product_id={$item->product_id} | variant_id={$item->variant_id} | qty={$item->qty} | date={$date}";
                            $this->line($message);
                            $this->writeLog($message);
                        }

                        continue;
                    }

                    $prefix = $c['party_type'] === 'supplier' ? 'SUP' : 'ORD';
                    $orderNumber = $prefix . '-' . str_replace('-', '', $date) . '-' . (int) $c['actor_id'];

                    $orderId = DB::table('orders')->insertGetId([
                        'order_type' => 'subscription',
                        'customer_id' => (int) $c['actor_id'],
                        'vendor_id'   => $c['party_type'] === 'supplier' ? (int) $c['actor_id'] : null,
                        'zone_id' => $c['zone_id'] ? (int) $c['zone_id'] : null,
                        'delivery_date' => $date,
                        'delivery_status' => 'pending',
                        'draft_order_id' => $c['draft_order_id'] ? (int) $c['draft_order_id'] : null,
                        'number' => $orderNumber,
                        'item_count' => 0,
                        'subtotal' => 0,
                        'tax' => 0,
                        'discount' => 0,
                        'total' => 0,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $createdForDate++;
                    $totalCreated++;

                    $this->writeLog("[CREATE] order created | order_id={$orderId} | party_type={$c['party_type']} | actor_id={$c['actor_id']} | actor_name={$c['actor_name']} | zone_id={$c['zone_id']} | draft_order_id={$c['draft_order_id']} | date={$date}");
                }

                if ($isDryRun) {
                    continue;
                }

                foreach ($c['items'] as $item) {
                    $existingOrderItem = DB::table('order_items')
                        ->where('order_id', $orderId)
                        ->where('product_id', $item->product_id)
                        ->where('variant_id', $item->variant_id)
                        ->first();

                    if ($existingOrderItem) {
                        $this->writeLog("[SKIP ITEM] order_item already exists | order_id={$orderId} | doi_id={$item->doi_id} | product_id={$item->product_id} | variant_id={$item->variant_id} | date={$date}");
                        continue;
                    }

                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'product_id' => (int) $item->product_id,
                        'variant_id' => (int) $item->variant_id,

                        'sku' => $item->sku ?? null,
                        'title' => $this->buildOrderItemTitle($item->product_title ?? null, $item->variant_title ?? null),
                        'variant' => $item->variant_title ?? null,
                        'brand' => $item->brand ?? null,
                        'product_url' => !empty($item->product_handle)
                            ? 'https://leelashop.in/products/' . ltrim($item->product_handle, '/')
                            : null,
                        'image_url' => $item->product_image ?? null,

                        'quantity' => (int) $item->qty,
                        'unit_price' => $item->price_snapshot !== null ? (float) $item->price_snapshot : 0,
                        'line_total' => $item->price_snapshot !== null
                            ? ((float) $item->qty * (float) $item->price_snapshot)
                            : 0,

                        'actuals_date' => $date,
                        'meta' => json_encode([
                            'doi_id' => $item->doi_id,
                            'draft_order_id' => $c['draft_order_id'],
                            'frequency_type' => $item->frequency_type,
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->writeLog("[CREATE ITEM] order_item created | order_id={$orderId} | doi_id={$item->doi_id} | product_id={$item->product_id} | variant_id={$item->variant_id} | qty={$item->qty} | date={$date}");
                }
                $orderTotals = DB::table('order_items')
                    ->where('order_id', $orderId)
                    ->selectRaw('
        COALESCE(SUM(quantity), 0) as item_count,
        COALESCE(SUM(line_total), 0) as subtotal
    ')
                    ->first();

                $subtotal = $orderTotals ? (float) $orderTotals->subtotal : 0;
                $itemCount = $orderTotals ? (int) $orderTotals->item_count : 0;
                $tax = 0;
                $discount = 0;
                $total = $subtotal + $tax - $discount;

                DB::table('orders')
                    ->where('id', $orderId)
                    ->update([
                        'item_count' => $itemCount,
                        'subtotal' => $subtotal,
                        'tax' => $tax,
                        'discount' => $discount,
                        'total' => $total,
                        'updated_at' => now(),
                    ]);

                $this->writeLog("[UPDATE ORDER TOTALS] order_id={$orderId} | item_count={$itemCount} | subtotal={$subtotal} | tax={$tax} | discount={$discount} | total={$total}");
            }
            $summary = "Date {$date} => created: {$createdForDate}, skipped: {$skippedForDate}";
            $this->info($summary);
            $this->writeLog($summary);
        }

        $finalSummary = $isDryRun
            ? "[DRY RUN COMPLETE] Total would create: {$totalCreated}, total skipped: {$totalSkipped}"
            : "[DONE] Total created: {$totalCreated}, total skipped: {$totalSkipped}";

        $this->info($finalSummary);
        $this->writeLog($finalSummary);

        if ($isDryRun && $this->logFilePath) {
            $this->info("Dry run details saved to: {$this->logFilePath}");
        }

        return self::SUCCESS;
    }

    protected function resolveDates(): array
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to = $this->option('to');

        if ($date && ($from || $to)) {
            $this->error('Use either --date or --from/--to, not both.');
            return [];
        }

        if (($from && !$to) || (!$from && $to)) {
            $this->error('Both --from and --to are required for date range.');
            return [];
        }

        if ($date) {
            return [Carbon::parse($date)->toDateString()];
        }

        if ($from && $to) {
            $fromDate = Carbon::parse($from);
            $toDate = Carbon::parse($to);

            if ($fromDate->gt($toDate)) {
                $this->error('--from date cannot be greater than --to date.');
                return [];
            }

            $dates = [];
            foreach (CarbonPeriod::create($fromDate, $toDate) as $day) {
                $dates[] = $day->toDateString();
            }

            return $dates;
        }

        return [Carbon::today()->toDateString()];
    }

    protected function writeLog(string $message): void
    {
        if (!$this->logFilePath) {
            return;
        }

        File::append($this->logFilePath, $message . PHP_EOL);
    }

    protected function matchesFrequency($item, Carbon $date): bool
    {
        $start = Carbon::parse($item->start_date);
        $meta = [];

        if (!empty($item->meta)) {
            $decoded = json_decode($item->meta, true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        return match ($item->frequency_type) {
            'daily' => true,

            'alternate_days' => $this->matchesAlternatePattern($start, $date, $meta),

            'weekdays' => $date->isWeekday(),

            'weekends' => $date->isWeekend(),

            'sat' => $date->isSaturday(),

            'sun' => $date->isSunday(),

            default => false,
        };
    }

    protected function matchesAlternatePattern(Carbon $start, Carbon $date, array $meta = []): bool
    {
        $patternStartValue = (int) ($meta['pattern_start_value'] ?? 1);

        // your final business rule: alternate must start with 1
        if ($patternStartValue !== 1) {
            return false;
        }

        $diff = $start->diffInDays($date);

        return $diff % 2 === 0;
    }
    protected function buildOrderItemTitle(?string $productTitle, ?string $variantTitle): string
    {
        $productTitle = trim((string) $productTitle);
        $variantTitle = trim((string) $variantTitle);

        if ($productTitle === '' && $variantTitle === '') {
            return 'Subscription Item';
        }

        if ($productTitle === '') {
            return $variantTitle;
        }

        if ($variantTitle === '') {
            return $productTitle;
        }

        // same text → keep only one
        if (strcasecmp($productTitle, $variantTitle) === 0) {
            return $productTitle;
        }

        // if variant already exists inside product title → keep only product title
        if (stripos($productTitle, $variantTitle) !== false) {
            return $productTitle;
        }

        return $productTitle . ' ' . $variantTitle;
    }
}
