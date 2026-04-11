<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GenerateDailySupplierOrders extends Command
{
    // file for generating daily supplier orders based on active supplier subscriptions (draft order items with supplier change requests)
    // php artisan dayli:generate-daily-supplier-orders --date=2026-04-01
    // php artisan dayli:generate-daily-supplier-orders --from=2026-04-01 --to=2026-04-11
    protected $signature = 'dayli:generate-daily-supplier-orders {--date=} {--from=} {--to=} {--dry}';
    protected $description = 'Create pending daily supplier orders for active vendor subscriptions';

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
            $this->logFilePath = storage_path("logs/daily-supplier-orders-dry-run-{$timestamp}.log");
            File::put($this->logFilePath, "DRY RUN - GenerateDailySupplierOrders\n");
            File::append($this->logFilePath, "Generated at: " . now()->toDateTimeString() . "\n\n");
            $this->info("Dry run log file: {$this->logFilePath}");
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($dates as $date) {
            $this->line("Processing supplier date: {$date}");

            $items = DB::table('draft_order_items as doi')
                ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
                ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
                ->leftJoin('users as u', 'u.id', '=', 'scr.for_user_id')
                ->leftJoin('products as p', 'p.product_id', '=', 'doi.product_id')
                ->leftJoin('variants as v', 'v.variant_id', '=', 'doi.variant_id')
                ->where('scr.party_type', 'supplier')
                ->where('doi.status', 'active')
                ->whereNotNull('scr.for_user_id')
                ->whereDate('doi.start_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('doi.end_date')
                        ->orWhereDate('doi.end_date', '>=', $date);
                })
                ->where('doi.qty', '>', 0)
                ->select([
                    'scr.for_user_id as vendor_user_id',
                    'u.display_name as vendor_name',
                    'u.name as vendor_raw_name',
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

                if (!$item->frequency_type) {
                    continue;
                }

                if (!$this->matchesFrequency($item, $currentDate)) {
                    continue;
                }

                $vendorId = (int) ($item->vendor_id ?: $item->vendor_user_id);

                $this->writeLog("DEBUG | vendor_id={$vendorId} | date={$date} | freq={$item->frequency_type} | start={$item->start_date}");

                $key = $vendorId . '_' . $date;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'vendor_id' => $vendorId,
                        'vendor_name' => $item->vendor_name ?: $item->vendor_raw_name,
                        'zone_id' => $item->zone_id,
                        'draft_order_id' => $item->draft_order_id,
                        'items' => [],
                    ];
                }

                $grouped[$key]['items'][] = $item;
            }

            foreach ($grouped as $g) {
                $existingOrder = DB::table('orders')
                    ->where('vendor_id', $g['vendor_id'])
                    ->whereDate('delivery_date', $date)
                    ->where('order_type', 'subscription')
                    ->first();

                if ($existingOrder) {
                    $orderId = $existingOrder->id;
                    $skippedForDate++;
                    $totalSkipped++;

                    $this->writeLog("[SKIP ORDER CREATE] supplier order already exists | vendor_id={$g['vendor_id']} | vendor_name={$g['vendor_name']} | zone_id={$g['zone_id']} | date={$date}");
                } else {
                    if ($isDryRun) {
                        $createdForDate++;
                        $totalCreated++;

                        $message = "[DRY] would create supplier order | vendor_id={$g['vendor_id']} | vendor_name={$g['vendor_name']} | zone_id={$g['zone_id']} | draft_order_id={$g['draft_order_id']} | date={$date}";
                        $this->line($message);
                        $this->writeLog($message);

                        foreach ($g['items'] as $item) {
                            $message = "[DRY] would create supplier order_item | vendor_id={$g['vendor_id']} | doi_id={$item->doi_id} | product_id={$item->product_id} | variant_id={$item->variant_id} | qty={$item->qty} | date={$date}";
                            $this->line($message);
                            $this->writeLog($message);
                        }

                        continue;
                    }

                    $orderNumber = 'SUP-' . str_replace('-', '', $date) . '-' . (int) $g['vendor_id'];

                    $orderId = DB::table('orders')->insertGetId([
                        'order_type' => 'subscription',
                        'customer_id' => (int) $g['vendor_id'],
                        'vendor_id' => (int) $g['vendor_id'],
                        'zone_id' => $g['zone_id'] ? (int) $g['zone_id'] : null,
                        'delivery_date' => $date,
                        'delivery_status' => 'pending',
                        'draft_order_id' => $g['draft_order_id'] ? (int) $g['draft_order_id'] : null,
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

                    $this->writeLog("[CREATE] supplier order created | order_id={$orderId} | vendor_id={$g['vendor_id']} | vendor_name={$g['vendor_name']} | zone_id={$g['zone_id']} | draft_order_id={$g['draft_order_id']} | date={$date}");
                }

                if ($isDryRun) {
                    continue;
                }

                foreach ($g['items'] as $item) {
                    $existingOrderItem = DB::table('order_items')
                        ->where('order_id', $orderId)
                        ->where('product_id', $item->product_id)
                        ->where('variant_id', $item->variant_id)
                        ->first();

                    if ($existingOrderItem) {
                        $this->writeLog("[SKIP ITEM] supplier order_item already exists | order_id={$orderId} | doi_id={$item->doi_id} | product_id={$item->product_id} | variant_id={$item->variant_id} | date={$date}");
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
                            'draft_order_id' => $g['draft_order_id'],
                            'frequency_type' => $item->frequency_type,
                            'party_type' => 'supplier',
                        ]),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $this->writeLog("[CREATE ITEM] supplier order_item created | order_id={$orderId} | doi_id={$item->doi_id} | product_id={$item->product_id} | variant_id={$item->variant_id} | qty={$item->qty} | date={$date}");
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

                $this->writeLog("[UPDATE ORDER TOTALS] supplier order_id={$orderId} | item_count={$itemCount} | subtotal={$subtotal} | tax={$tax} | discount={$discount} | total={$total}");
            }

            $summary = "Date {$date} => supplier created: {$createdForDate}, supplier skipped: {$skippedForDate}";
            $this->info($summary);
            $this->writeLog($summary);
        }

        $finalSummary = $isDryRun
            ? "[DRY RUN COMPLETE] Supplier total would create: {$totalCreated}, total skipped: {$totalSkipped}"
            : "[DONE] Supplier total created: {$totalCreated}, total skipped: {$totalSkipped}";

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

        if (strcasecmp($productTitle, $variantTitle) === 0) {
            return $productTitle;
        }

        if (stripos($productTitle, $variantTitle) !== false) {
            return $productTitle;
        }

        return $productTitle . ' ' . $variantTitle;
    }
}
