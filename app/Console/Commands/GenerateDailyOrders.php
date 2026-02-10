<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateDailyOrders extends Command
{
    protected $signature = 'dayli:generate-daily-orders {--date=} {--dry}';
    protected $description = 'Create pending daily orders for active subscriptions';

    public function handle()
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : Carbon::today()->toDateString();

        // ✅ Get all customers who have active subscription items (same base as your myWorkOrders)
        $customers = DB::table('draft_order_items as doi')
            ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
            ->where('do.status', 'active')
            ->where('doi.status', 'active')
            ->whereNotNull('scr.for_user_id')
            ->select([
                'scr.for_user_id as customer_id',
                'scr.zone_id as zone_id',
                'do.id as draft_order_id',
            ])
            ->distinct()
            ->get();

        $created = 0;
        $isDryRun = (bool) $this->option('dry');

        foreach ($customers as $c) {
            // ✅ Insert only if not exists (unique key also protects)
            $exists = DB::table('orders')
                ->where('customer_id', $c->customer_id)
                ->whereDate('delivery_date', $date)
                ->exists();

            if ($exists) continue;

            if ($isDryRun) {
                $this->line(
                    "[DRY] would create order | customer_id={$c->customer_id} | zone_id={$c->zone_id} | date={$date}"
                );
                $created++;
                continue;
            }

            DB::table('orders')->insert([
                'order_type' => 'subscription',
                'customer_id' => (int) $c->customer_id,
                'zone_id' => $c->zone_id ? (int) $c->zone_id : null,
                'delivery_date' => $date,
                'delivery_status' => 'pending',
                'draft_order_id' => $c->draft_order_id ? (int) $c->draft_order_id : null,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $created++;
        }

        if ($isDryRun) {
            $this->info("[DRY RUN] Orders that would be created for {$date}: {$created}");
        } else {
            $this->info("Generated daily orders for {$date}. Created: {$created}");
        }
        return 0;
    }
}
