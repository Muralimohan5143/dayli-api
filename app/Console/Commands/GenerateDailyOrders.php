<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

class GenerateDailyOrders extends Command
{
    protected $signature = 'dayli:generate-daily-orders {--date=} {--from=} {--to=} {--dry}';
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
                ->where('do.status', 'active')
                ->where('doi.status', 'active')
                ->whereNotNull('scr.for_user_id')
                ->whereDate('doi.start_date', '<=', $date)
                ->where(function ($q) use ($date) {
                    $q->whereNull('doi.end_date')
                        ->orWhereDate('doi.end_date', '>=', $date);
                })
                ->where('doi.qty', '>', 0)
                ->select([
                    'scr.for_user_id as customer_id',
                    'u.display_name as customer_name',
                    'scr.zone_id as zone_id',
                    'do.id as draft_order_id',
                    'doi.id as doi_id',
                    'doi.frequency_type',
                    'doi.start_date',
                ])
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
                $this->writeLog("DEBUG | customer={$item->customer_id} | date={$date} | freq={$item->frequency_type} | start={$item->start_date}");

                // 🔥 ADD HERE (exact place)
                if ($item->frequency_type === 'alternate_days') {
                    $start = Carbon::parse($item->start_date);

                    if ($start->diffInDays($currentDate) < 0) {
                        continue;
                    }
                }

                $key = $item->customer_id . '_' . $date;

                if (!isset($grouped[$key])) {
                    $grouped[$key] = [
                        'customer_id' => $item->customer_id,
                        'customer_name' => $item->customer_name,
                        'zone_id' => $item->zone_id,
                        'draft_order_id' => $item->draft_order_id,
                    ];
                }
            }
            foreach ($grouped as $c) {

                $exists = DB::table('orders')
                    ->where('customer_id', $c['customer_id'])
                    ->whereDate('delivery_date', $date)
                    ->exists();

                if ($exists) {
                    $skippedForDate++;
                    $totalSkipped++;

                    $this->writeLog("[SKIP] order already exists | customer_id={$c['customer_id']} | customer_name={$c['customer_name']} | zone_id={$c['zone_id']} | date={$date}");
                    continue;
                }

                if ($isDryRun) {
                    $createdForDate++;
                    $totalCreated++;

                    $message = "[DRY] would create order | customer_id={$c['customer_id']} | customer_name={$c['customer_name']} | zone_id={$c['zone_id']} | draft_order_id={$c['draft_order_id']} | date={$date}";
                    $this->line($message);
                    $this->writeLog($message);
                    continue;
                }

                DB::table('orders')->insert([
                    'order_type' => 'subscription',
                    'customer_id' => (int) $c['customer_id'],
                    'zone_id' => $c['zone_id'] ? (int) $c['zone_id'] : null,
                    'delivery_date' => $date,
                    'delivery_status' => 'pending',
                    'draft_order_id' => $c['draft_order_id'] ? (int) $c['draft_order_id'] : null,
                    'status' => 'pending',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $createdForDate++;
                $totalCreated++;

                $this->writeLog("[CREATE] order created | customer_id={$c['customer_id']} | customer_name={$c['customer_name']} | zone_id={$c['zone_id']} | draft_order_id={$c['draft_order_id']} | date={$date}");
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

        return match ($item->frequency_type) {
            'daily' => true,

            'alternate_days' =>
            $start->diffInDays($date) % 2 === 0,

            'weekdays' =>
            $date->isWeekday(),

            'weekends' =>
            $date->isWeekend(),

            'sat' =>
            $date->isSaturday(),

            'sun' =>
            $date->isSunday(),

            default => false,
        };
    }
}
