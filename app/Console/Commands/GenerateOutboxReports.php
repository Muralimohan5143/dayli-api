<?php

namespace App\Console\Commands;

use App\Models\OutboxReport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GenerateOutboxReports extends Command
{
    protected $signature = 'reports:generate-outbox {--month=}';
    protected $description = 'Generate monthly outbox report rows for zone managers based on real order item data';

    public function handle(): int
    {
        $month = $this->option('month');

        if ($month) {
            [$start, $end] = $this->resolveMonthRange();

            $this->info(
                "Generating outbox reports for {$start->toDateString()} to {$end->toDateString()}"
            );

            $stats = $this->generateForMonth($start, $end);

            $this->newLine();
            $this->info(
                "Done for {$start->format('Y-m')}. created={$stats['created']}, updated={$stats['updated']}, skipped={$stats['skipped']}"
            );

            return self::SUCCESS;
        }

        $firstMonth = $this->getFirstOrderMonth();

        if (!$firstMonth) {
            $this->warn('No order data found.');
            return self::SUCCESS;
        }

        $lastCompletedMonth = now()->subMonthNoOverflow()->startOfMonth();

        if ($firstMonth->gt($lastCompletedMonth)) {
            $this->warn('No completed months available yet.');
            return self::SUCCESS;
        }

        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        $cursor = $firstMonth->copy();

        while ($cursor->lte($lastCompletedMonth)) {
            $start = $cursor->copy()->startOfMonth();
            $end = $cursor->copy()->endOfMonth();

            $this->info(
                "Checking/generating outbox reports for {$start->toDateString()} to {$end->toDateString()}"
            );

            $stats = $this->generateForMonth($start, $end);

            $totalCreated += $stats['created'];
            $totalUpdated += $stats['updated'];
            $totalSkipped += $stats['skipped'];

            $cursor->addMonthNoOverflow();
        }

        $this->newLine();
        $this->info(
            "Done. created={$totalCreated}, updated={$totalUpdated}, skipped={$totalSkipped}"
        );

        return self::SUCCESS;
    }

    /**
     * Resolve target month range.
     *
     * --month=2026-03 => 2026-03-01 to 2026-03-31
     * no --month      => previous month
     *
     * @return array{0: \Carbon\Carbon, 1: \Carbon\Carbon}
     */
    protected function resolveMonthRange(): array
    {
        $month = $this->option('month');

        if ($month) {
            try {
                $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
                $end = Carbon::createFromFormat('Y-m', $month)->endOfMonth();
            } catch (\Throwable $e) {
                $this->error("Invalid --month format. Use YYYY-MM, example: --month=2026-03");
                exit(self::FAILURE);
            }
        } else {
            $start = now()->subMonthNoOverflow()->startOfMonth();
            $end = now()->subMonthNoOverflow()->endOfMonth();
        }

        return [$start, $end];
    }

    protected function getFirstOrderMonth(): ?Carbon
    {
        $minDate = DB::table('order_items')
            ->whereNotNull('actuals_date')
            ->min('actuals_date');

        if (!$minDate) {
            return null;
        }

        return Carbon::parse($minDate)->startOfMonth();
    }

    protected function generateForMonth(Carbon $start, Carbon $end): array
    {
        $rows = $this->getMonthlyReportSourceRows($start, $end);

        if ($rows->isEmpty()) {
            $this->warn("No eligible order data found for {$start->format('Y-m')}.");
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
            ];
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $zoneId = (int) $row->zone_id;
            $subscriptionTypeId = (int) $row->subscription_type_id;

            if (!$zoneId || !$subscriptionTypeId) {
                $skipped++;
                $this->line(
                    "Skipped invalid row | zone_id={$row->zone_id} | subscription_type_id={$row->subscription_type_id}"
                );
                continue;
            }

            $zoneManagerId = 11306;

            $attributes = [
                'zone_manager_id' => (int) $zoneManagerId,
                'report_type' => 'monthly_invoice',
                'subscription_type_id' => $subscriptionTypeId,
                'service_type_id' => null,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
            ];

            $values = [
                'status' => 'pending',
                'payload_json' => [
                    'month' => $start->format('Y-m'),
                    'zone_id' => $zoneId,
                    'subscription_type_id' => $subscriptionTypeId,
                    'item_count' => (int) $row->item_count,
                    'order_count' => (int) $row->order_count,
                ],
            ];

            $report = OutboxReport::updateOrCreate($attributes, $values);

            if ($report->wasRecentlyCreated) {
                $created++;
                $this->info(
                    "Created | month={$start->format('Y-m')} | zm={$zoneManagerId} | zone={$zoneId} | sub_type={$subscriptionTypeId}"
                );
            } else {
                $updated++;
                $this->line(
                    "Updated existing | month={$start->format('Y-m')} | zm={$zoneManagerId} | zone={$zoneId} | sub_type={$subscriptionTypeId}"
                );
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ];
    }

    /**
     * Map zone_id => zone_manager_id.
     *
     * Assumes zones table has manager_id column.
     * If your DB uses a different column name, change it here only.
     */
    // protected function getZoneManagerMap(): Collection
    // {
    //     return DB::table('zones')
    //         ->whereNotNull('manager_id')
    //         ->pluck('manager_id', 'id');
    // }

    /**
     * Build source rows only from real order data.
     *
     * Mapping used:
     * order_items.variant_id
     * -> variants.product_id
     * -> products.product_sub_type
     * -> subscription_sub_types.slug
     * -> subscription_sub_types.subscription_type_id
     */
    protected function getMonthlyReportSourceRows(Carbon $start, Carbon $end): Collection
    {
        return DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('variants as v', 'v.variant_id', '=', 'oi.variant_id')
            ->join('products as p', 'p.product_id', '=', 'v.product_id')
            ->join('subscription_sub_types as sst', 'sst.slug', '=', 'p.product_sub_type')
            ->select([
                'o.zone_id',
                'sst.subscription_type_id',
                DB::raw('COUNT(*) as item_count'),
                DB::raw('COUNT(DISTINCT o.id) as order_count'),
            ])
            ->whereNotNull('o.zone_id')
            ->whereNotNull('oi.variant_id')
            ->whereNotNull('p.product_sub_type')
            ->whereBetween('oi.actuals_date', [
                $start->toDateString(),
                $end->toDateString(),
            ])
            ->groupBy('o.zone_id', 'sst.subscription_type_id')
            ->orderBy('o.zone_id')
            ->orderBy('sst.subscription_type_id')
            ->get();
    }
}
