<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExportInteraktBillingCsv extends Command
{
    protected $signature = 'report:interakt-billing
        {--month= : Month in YYYY-MM (default: current month)}
        {--zone= : Filter by zone_id (optional)}
        {--status= : Comma statuses to include. Default: draft,pending,confirmed,fulfilled}
        {--country=91 : Country code for Interakt}
        {--customer-type=milk-customer : Customer type label}
        {--output= : Output path (default: storage/app/reports/interakt_billing_YYYY_MM.csv)}
    ';

    protected $description = 'Export monthly billing + delivery summary per customer for Interakt (CSV), using draft_order_items start_date/qty for expected schedule.';

    public function handle(): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("Invalid --month. Use YYYY-MM (example: 2025-12)");
            return 1;
        }

        $monthStart = $month . '-01';
        $monthEndExclusive = date('Y-m-d', strtotime($monthStart . ' +1 month')); // exclusive
        $monthEndInclusive = date('Y-m-d', strtotime($monthEndExclusive . ' -1 day'));

        $zoneId = $this->option('zone');

        $statusOpt = $this->option('status');
        $statuses = $statusOpt
            ? array_filter(array_map('trim', explode(',', (string)$statusOpt)))
            : ['draft', 'pending', 'confirmed', 'fulfilled'];

        $country = (string)$this->option('country');
        $customerType = (string)$this->option('customer-type');

        $defaultOut = "reports/interakt_billing_{$month}.csv";
        $outPath = $this->option('output') ?: $defaultOut;

        // ------------------------------------------------------------
        // 1) Pull actual deliveries from orders + order_items for month
        // ------------------------------------------------------------
        $q = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->leftJoin('users as u', 'u.id', '=', 'o.customer_id')
            ->select([
                'o.id as order_id',
                'o.customer_id',
                'o.zone_id',
                'o.draft_order_id',
                DB::raw("COALESCE(NULLIF(TRIM(u.phone), ''), NULLIF(TRIM(o.phone), '')) as customer_phone"),
                DB::raw("COALESCE(NULLIF(TRIM(u.display_name), ''), NULLIF(TRIM(u.name), ''), NULLIF(TRIM(o.name), ''), 'Customer') as customer_name"),

                'oi.actuals_date as actuals_date',
                'o.current_shipping as order_shipping',

                'oi.title as item_title',
                'oi.variant_id as item_variant_id',
                'oi.quantity as item_qty',
                'oi.unit_price as item_unit_price',
                'oi.line_total as item_line_total',
            ])
            ->whereNull('o.deleted_at')
            ->whereNotNull('oi.actuals_date')
            ->whereBetween('oi.actuals_date', [$monthStart, $monthEndInclusive])

            ->whereIn('o.status', $statuses);

        if ($zoneId !== null && $zoneId !== '') {
            $q->where('o.zone_id', (int)$zoneId);
        }

        $rows = $q->orderBy('o.customer_id')->orderBy('oi.actuals_date')->get();


        if ($rows->isEmpty()) {
            $this->warn("No rows found for month={$month}. (Check date field + status filter)");
            return 0;
        }

        // Collect draft_order_ids that appear in this month’s orders
        $draftOrderIds = [];
        foreach ($rows as $r) {
            if (!empty($r->draft_order_id)) {
                $draftOrderIds[(int)$r->draft_order_id] = true;
            }
        }
        $draftOrderIds = array_keys($draftOrderIds);

        // ------------------------------------------------------------
        // 2) Load expected schedule from draft_order_items
        //    keyed by (draft_order_id + variant_id)
        // ------------------------------------------------------------
        $expectedMap = []; // [$draftOrderId][$variantId] => expectedItemData

        if (!empty($draftOrderIds)) {
            $items = DB::table('draft_order_items as doi')
                ->select([
                    'doi.draft_order_id',
                    'doi.variant_id',
                    'doi.product_id',
                    'doi.frequency_type',
                    'doi.qty',
                    'doi.start_date',
                    'doi.end_date',
                    'doi.status',
                    'doi.meta',
                ])
                ->whereIn('doi.draft_order_id', $draftOrderIds)
                ->whereIn('doi.status', ['active', 'paused']) // cancelled excluded
                ->get();

            foreach ($items as $it) {
                $dId = (int)$it->draft_order_id;
                $vId = (int)$it->variant_id;

                $expectedMap[$dId][$vId] = [
                    'frequency_type' => $it->frequency_type,
                    'qty' => (float)$it->qty,               // Daily Count
                    'start_date' => $it->start_date,        // expected window start
                    'end_date' => $it->end_date,            // expected window end
                    'status' => $it->status,
                    'meta' => $this->decodeJson($it->meta),
                ];
            }
        }

        // ------------------------------------------------------------
        // 3) Precompute per-order totals for shipping allocation
        // ------------------------------------------------------------
        $orderTotals = [];
        foreach ($rows as $r) {
            $oid = (int)$r->order_id;
            if (!isset($orderTotals[$oid])) {
                $orderTotals[$oid] = [
                    'shipping' => (float)$r->order_shipping,
                    'line_total_sum' => 0.0,
                ];
            }
            $orderTotals[$oid]['line_total_sum'] += (float)$r->item_line_total;
        }

        // ------------------------------------------------------------
        // 4) Aggregate per customer + product(title) using actuals
        //    and store per-day actual qty for exception compare
        // ------------------------------------------------------------
        $cust = [];
        foreach ($rows as $r) {
            $customerId = (int)$r->customer_id;

            $name = trim((string)$r->customer_name);
            $phone = preg_replace('/\D+/', '', (string)$r->customer_phone);
            if (strlen($phone) > 10) {
                $phone = substr($phone, -10); // keep last 10 digits
            }
            $title = trim((string)$r->item_title);
            $variantId = (int)($r->item_variant_id ?? 0);
            $draftOrderId = (int)($r->draft_order_id ?? 0);

            if ($title === '') $title = 'Unknown';

            if (!isset($cust[$customerId])) {
                $cust[$customerId] = [
                    'name' => $name ?: 'Customer',
                    'phone' => $phone ?: '',
                    'previous_dues' => 0.0, // TODO hook later
                    'products' => [],
                ];
            }

            if (!isset($cust[$customerId]['products'][$title])) {
                // expected defaults
                $exp = $expectedMap[$draftOrderId][$variantId] ?? null;

                $dailyCount = $exp ? (float)$exp['qty'] : null;
                $freq = $exp['frequency_type'] ?? null;
                $startDate = $exp['start_date'] ?? null;
                $endDate = $exp['end_date'] ?? null;
                $meta = $exp['meta'] ?? null;

                $cust[$customerId]['products'][$title] = [
                    'draft_order_id' => $draftOrderId,
                    'variant_id' => $variantId,

                    'mrp' => (float)$r->item_unit_price,
                    'monthly_count' => 0.0,
                    'milk_total' => 0.0,
                    'delivery_fee' => 0.0,
                    'days_actual' => [], // day => actual qty

                    // expected schedule
                    'daily_count' => $dailyCount,      // from draft_order_items.qty
                    'frequency_type' => $freq,         // from draft_order_items.frequency_type
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'meta' => $meta,
                ];
            }

            // actual totals
            $cust[$customerId]['products'][$title]['monthly_count'] += (float)$r->item_qty;
            $cust[$customerId]['products'][$title]['milk_total'] += (float)$r->item_line_total;

            // actual per day
            $day = (int)date('j', strtotime((string)$r->actuals_date)); // ✅ use actuals_date

            if (!isset($cust[$customerId]['products'][$title]['days_actual'][$day])) {
                $cust[$customerId]['products'][$title]['days_actual'][$day] = 0.0;
            }
            $cust[$customerId]['products'][$title]['days_actual'][$day] += (float)$r->item_qty;

            // allocate shipping per item
            $oid = (int)$r->order_id;
            $shipping = (float)$orderTotals[$oid]['shipping'];
            $sumLine = (float)$orderTotals[$oid]['line_total_sum'];
            $share = ($shipping > 0 && $sumLine > 0) ? ((float)$r->item_line_total / $sumLine) : 0.0;
            $cust[$customerId]['products'][$title]['delivery_fee'] += $shipping * $share;
        }

        // ------------------------------------------------------------

        // ------------------------------------------------------------
        // 4b) Compute previous dues per customer:
        //     sum(historical invoices) - sum(historical inward payments)
        //     before monthStart
        // ------------------------------------------------------------
        $customerIds = array_keys($cust);
        $prevDueMap = [];

        if (!empty($customerIds)) {
            $invSums = DB::table('invoices as inv')
                ->join('orders as oinv', 'oinv.id', '=', 'inv.order_id')
                ->select([
                    'oinv.customer_id as customer_id',
                    DB::raw('SUM(inv.grand_total) as inv_total'),
                ])
                ->whereNull('inv.deleted_at')
                ->whereNotNull('inv.invoice_date')
                ->where('inv.invoice_date', '<', $monthStart)
                ->where('inv.status', '!=', 'void')
                ->groupBy('oinv.customer_id')
                ->get();

            foreach ($invSums as $r) {
                $prevDueMap[(int)$r->customer_id]['inv'] = (float)$r->inv_total;
            }

            // payments can be linked via invoice_id OR directly via order_id
            $paySums = DB::table('inward_payments as ip')
                ->leftJoin('invoices as invp', 'invp.id', '=', 'ip.invoice_id')
                ->leftJoin('orders as oinvp', 'oinvp.id', '=', 'invp.order_id')
                ->leftJoin('orders as oip', 'oip.id', '=', 'ip.order_id')
                ->select([
                    DB::raw('COALESCE(oinvp.customer_id, oip.customer_id) as customer_id'),
                    DB::raw('SUM(ip.amount) as paid_total'),
                ])
                ->whereNull('ip.deleted_at')
                ->whereNotNull('ip.payment_date')
                ->where('ip.payment_date', '<', $monthStart)
                ->groupBy(DB::raw('COALESCE(oinvp.customer_id, oip.customer_id)'))
                ->get();

            foreach ($paySums as $r) {
                $cid = (int)$r->customer_id;
                if ($cid > 0) {
                    $prevDueMap[$cid]['paid'] = (float)$r->paid_total;
                }
            }

            foreach ($customerIds as $cid) {
                $inv = (float)($prevDueMap[$cid]['inv'] ?? 0.0);
                $paid = (float)($prevDueMap[$cid]['paid'] ?? 0.0);
                $cust[$cid]['previous_dues'] = $inv - $paid; // can be negative (credit)
            }
        }

        // 5) Build Interakt CSV output
        // ------------------------------------------------------------
        $csvLines = [];

        foreach ($cust as $customerId => $cdata) {
            $name = $cdata['name'] ?: 'Customer';
            $phone = $cdata['phone'] ?: '';

            $customerTotal = 0.0;
            $productBlocks = [];

            foreach ($cdata['products'] as $title => $pdata) {
                $mrp = (float)$pdata['mrp'];
                $monthlyCount = $pdata['monthly_count'];
                $milkTotal = (float)$pdata['milk_total'];
                $deliveryCount = (int)round((float)$monthlyCount);
                $deliveryFee = $this->calcDeliveryFee($deliveryCount, $title); // formula-based
                $pdata['delivery_fee'] = $deliveryFee; // keep for debugging

                // expected daily count from draft_order_items
                $dailyCount = $pdata['daily_count'];
                if ($dailyCount === null) {
                    // fallback if missing in expected map
                    $dailyCount = $this->inferDailyCountFromActual($pdata['days_actual']);
                }

                // exceptions based on schedule
                $exceptions = $this->buildExceptionsUsingSchedule(
                    $monthStart,
                    $monthEndInclusive,
                    $dailyCount,
                    (string)($pdata['frequency_type'] ?? 'daily'),
                    $pdata['start_date'],
                    $pdata['end_date'],
                    $pdata['meta'],
                    $pdata['days_actual']
                );

                $subTotal = $milkTotal + $deliveryFee;
                $customerTotal += $subTotal;

                $block = sprintf(
                    '%s, MRP=%s/-, Monthly Count=%s,Daily Count=%s, Exceptions:%s, Milk/Curd total: (%s x %s)=₹%s/-, Delivery fee: ₹%s/-, *SubTotal: ₹ %s/-*,',
                    $title,
                    $this->moneyNoDecimals($mrp),
                    $this->moneyNoDecimals($monthlyCount),
                    $this->moneyNoDecimals($dailyCount),
                    $exceptions,
                    $this->moneyNoDecimals($mrp),
                    $this->moneyNoDecimals($monthlyCount),
                    $this->moneyNoDecimals($milkTotal),
                    $this->moneyNoDecimals($deliveryFee),
                    $this->moneyNoDecimals($subTotal)
                );

                $productBlocks[] = $block;
            }

            $prev = (float)$cdata['previous_dues']; // TODO later
            $finalTotal = $customerTotal + $prev;

            $fields = [];
            $fields[] = $name;
            $fields[] = $phone;
            $fields[] = $country;
            $fields[] = $customerType;
            $fields[] = '*₹' . $this->moneyNoDecimals($finalTotal) . '/-*';

            foreach ($productBlocks as $pb) {
                $fields[] = $pb;
            }

            $csvLines[] = $this->toCsvLine($fields);
        }

        Storage::disk('local')->put($outPath, implode("\n", $csvLines) . "\n");
        $this->info("Exported: storage/app/{$outPath}");

        return 0;
    }

    // ---------------------------
    // Exception logic (schedule)
    // ---------------------------
    private function buildExceptionsUsingSchedule(
        string $monthStart,
        string $monthEnd,
        float $dailyCount,
        string $frequencyType,
        ?string $startDate,
        ?string $endDate,
        $meta,
        array $daysActual
    ): string {
        // Effective window = month ∩ (start_date..end_date)
        $effStart = $startDate ? max($monthStart, $startDate) : $monthStart;
        $effEnd   = $endDate ? min($monthEnd, $endDate) : $monthEnd;

        // If eff window invalid, treat as no schedule
        if (strtotime($effStart) > strtotime($effEnd)) {
            // If there are any actual deliveries, show them as exceptions
            $parts = [];
            foreach ($daysActual as $day => $qty) {
                if ((float)$qty !== 0.0) $parts[] = "Day{$day}=" . $this->moneyNoDecimals((float)$qty);
            }
            return empty($parts) ? 'N/A' : implode('|', $parts) . ', ';
        }

        // Build exceptions Day1..Day31 only for days that are scheduled OR have actual>0
        $parts = [];
        $monthDays = (int)date('t', strtotime($monthStart));

        for ($d = 1; $d <= $monthDays; $d++) {
            $date = date('Y-m-d', strtotime(substr($monthStart, 0, 7) . '-' . str_pad((string)$d, 2, '0', STR_PAD_LEFT)));

            $scheduled = $this->isScheduledOn($date, $effStart, $effEnd, $frequencyType, $meta);
            $expected = $scheduled ? $dailyCount : 0.0;
            $actual = (float)($daysActual[$d] ?? 0.0);

            if ($actual !== $expected) {
                // mimic Apps Script format DayX=value
                $parts[] = "Day{$d}=" . $this->moneyNoDecimals($actual);
            }
        }

        return empty($parts) ? 'N/A, ' : implode('|', $parts) . ', ';
    }

    private function isScheduledOn(string $date, string $effStart, string $effEnd, string $frequencyType, $meta): bool
    {
        if ($date < $effStart || $date > $effEnd) return false;

        $ft = strtolower(trim($frequencyType ?: 'daily'));

        $dow = (int)date('N', strtotime($date)); // 1=Mon..7=Sun

        switch ($ft) {
            case 'daily':
                return true;

            case 'weekdays':
                return $dow >= 1 && $dow <= 5;

            case 'weekends':
                return $dow === 6 || $dow === 7;

            case 'sat':
                return $dow === 6;

            case 'sun':
                return $dow === 7;

            case 'alternate_days':
                // Alternate starting from effStart: day 0 scheduled, day 1 not, day 2 scheduled...
                $diffDays = (int)floor((strtotime($date) - strtotime($effStart)) / 86400);
                return ($diffDays % 2) === 0;

            case 'custom':
                // If meta has custom_days like [1,3,5] meaning Mon/Wed/Fri (ISO N)
                if (is_array($meta) && isset($meta['custom_days']) && is_array($meta['custom_days'])) {
                    return in_array($dow, array_map('intval', $meta['custom_days']), true);
                }
                // fallback to daily if not defined
                return true;

            case 'on_demand':
                // on-demand has no expected schedule, but we still want to show actuals as exceptions
                return false;

            default:
                return true;
        }
    }

    private function inferDailyCountFromActual(array $daysActual): float
    {
        // mode of non-zero actual qty
        $freq = [];
        foreach ($daysActual as $d => $qty) {
            $q = (float)$qty;
            if ($q <= 0) continue;
            $key = (string)$q;
            $freq[$key] = ($freq[$key] ?? 0) + 1;
        }
        if (empty($freq)) return 1.0;
        arsort($freq);
        return (float)array_key_first($freq);
    }


    /**
     * Replicate Google Sheet delivery fee formula:
     * =if(LT(quotient(delivery_count, 25), 1),
     *     (delivery_count * IF(REGEXMATCH(LOWER(milk_type),"arokya"), 1, IF(REGEXMATCH(LOWER(milk_type),"small"), 1, 2))),
     *     IF(OR(REGEXMATCH(LOWER(milk_type),"arokya"),REGEXMATCH(LOWER(milk_type),"small")), delivery_count, quotient(delivery_count, 25) * 50))
     */
    private function calcDeliveryFee(int $deliveryCount, string $milkType): float
    {
        $lc = mb_strtolower($milkType);
        $isArokya = str_contains($lc, 'arokya');
        $isSmall = str_contains($lc, 'small');

        $q = intdiv(max($deliveryCount, 0), 25);

        if ($q < 1) {
            $mult = ($isArokya || $isSmall) ? 1 : 2;
            return $deliveryCount * $mult;
        }

        if ($isArokya || $isSmall) {
            return $deliveryCount;
        }

        return $q * 50;
    }

    private function decodeJson($val): ?array
    {
        if ($val === null) return null;
        if (is_array($val)) return $val;
        $s = trim((string)$val);
        if ($s === '') return null;
        $decoded = json_decode($s, true);
        return is_array($decoded) ? $decoded : null;
    }

    private function moneyNoDecimals(float $v): string
    {
        // Keep same style as sheet exports (integer-like)
        return (string)round($v);
    }

    private function toCsvLine(array $fields): string
    {
        $out = [];
        foreach ($fields as $f) {
            $s = (string)$f;
            $s = str_replace('"', '""', $s);
            $out[] = '"' . $s . '"';
        }
        return implode(',', $out);
    }
}
