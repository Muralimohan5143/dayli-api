<?php

namespace App\Ops\Handlers;

use App\Models\OutboxEvent;
use App\Ops\Contracts\EventHandler;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * VendorSupplyReconcileHandler
 *
 * ✅ Uses ONLY existing tables:
 * - orders (has vendor_id, delivery_date)
 * - order_items (has product_id, quantity, actuals_date)
 *
 * Assumptions (based on your schema):
 * - EXPECTED = sum(order_items.quantity) for orders of vendor_id on orders.delivery_date = target date
 * - ACTUAL   = sum(order_items.quantity) for orders of vendor_id where order_items.actuals_date = target date
 *
 * This handler is read-only (no new tables). It returns a JSON-friendly summary that your outbox worker
 * can store into outbox_events.result/status.
 */
class VendorSupplyReconcileHandler implements EventHandler
{
    public function handle(OutboxEvent $event): array
    {
        $payload = $event->payload ?? [];

        $vendorId = (int) ($payload['vendor_id'] ?? 0);
        $orderId  = (int) ($payload['order_id'] ?? ($event->aggregate_id ?? 0));

        if ($vendorId <= 0) {
            throw new \RuntimeException('Invalid payload: vendor_id required');
        }

        // delivery_date can be provided by the event producer. If not, default to today.
        $dateStr = (string) ($payload['delivery_date']
            ?? $payload['deliveryDate']
            ?? $payload['date']
            ?? Carbon::today()->toDateString());

        $targetDate = Carbon::parse($dateStr)->toDateString();

        // Optional filter if you start sending it in payload
        $subTypeId = (int) ($payload['subscription_type_id'] ?? $payload['subscriptionTypeId'] ?? 0);

        // --------------------------
        // EXPECTED: by orders.delivery_date
        // --------------------------
        $expectedQ = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.vendor_id', $vendorId)
            ->whereDate('o.delivery_date', $targetDate)
            // avoid cancelled orders
            ->whereNotIn('o.status', ['cancelled'])
            ->select('oi.product_id', DB::raw('SUM(oi.quantity) as qty'))
            ->groupBy('oi.product_id');

        if ($subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('orders', 'subscription_type_id')) {
            $expectedQ->where('o.subscription_type_id', $subTypeId);
        }

        $expectedRows = $expectedQ->get();

        $expected = [];
        $expectedTotal = 0.0;
        foreach ($expectedRows as $r) {
            $pid = (string) ($r->product_id ?? 0);
            if ($pid === '0') continue;
            $qty = (float) ($r->qty ?? 0);
            $expected[$pid] = $qty;
            $expectedTotal += $qty;
        }

        // --------------------------
        // ACTUAL: by order_items.actuals_date
        // --------------------------
        $actualQ = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.vendor_id', $vendorId)
            ->whereDate('oi.actuals_date', $targetDate)
            ->whereNotIn('o.status', ['cancelled'])
            ->select('oi.product_id', DB::raw('SUM(oi.quantity) as qty'))
            ->groupBy('oi.product_id');

        if ($subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('orders', 'subscription_type_id')) {
            $actualQ->where('o.subscription_type_id', $subTypeId);
        }

        $actualRows = $actualQ->get();

        $actual = [];
        $actualTotal = 0.0;
        foreach ($actualRows as $r) {
            $pid = (string) ($r->product_id ?? 0);
            if ($pid === '0') continue;
            $qty = (float) ($r->qty ?? 0);
            $actual[$pid] = $qty;
            $actualTotal += $qty;
        }

        // --------------------------
        // DIFF
        // --------------------------
        $keys = array_values(array_unique(array_merge(array_keys($expected), array_keys($actual))));

        $diff = [];
        $mismatches = [];
        foreach ($keys as $pid) {
            $e = (float) ($expected[$pid] ?? 0);
            $a = (float) ($actual[$pid] ?? 0);
            $d = $a - $e;

            $diff[$pid] = $d;

            if (abs($d) > 0.000001) {
                $mismatches[] = [
                    'product_id' => (int) $pid,
                    'expected'   => $e,
                    'actual'     => $a,
                    'diff'       => $d, // +ve = excess, -ve = shortage
                ];
            }
        }

        $status = count($mismatches) ? 'mismatch' : 'matched';

        // Helpful extra info: counts of orders
        $expectedOrdersQ = DB::table('orders')
            ->where('vendor_id', $vendorId)
            ->whereDate('delivery_date', $targetDate)
            ->whereNotIn('status', ['cancelled']);

        if ($subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('orders', 'subscription_type_id')) {
            $expectedOrdersQ->where('subscription_type_id', $subTypeId);
        }

        $expectedOrdersCount = (int) $expectedOrdersQ->count();

        $actualOrdersCount = (int) DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->where('o.vendor_id', $vendorId)
            ->whereDate('oi.actuals_date', $targetDate)
            ->whereNotIn('o.status', ['cancelled'])
            ->distinct('o.id')
            ->count('o.id');

        return [
            'ok' => true,
            'handler' => 'VendorSupplyReconcileHandler',

            'vendor_id' => $vendorId,
            // keep vendor_supply_id for correlation even if we don't read any supply table
            'order_id'  => $orderId,

            'delivery_date' => $targetDate,
            'subscription_type_id' => $subTypeId > 0 ? $subTypeId : null,

            'status' => $status,

            'orders' => [
                'expected_orders' => $expectedOrdersCount,
                'actual_orders' => $actualOrdersCount,
            ],

            'totals' => [
                'expected_qty' => $expectedTotal,
                'actual_qty' => $actualTotal,
                'diff_qty' => $actualTotal - $expectedTotal,
            ],

            // group-by-product info (JSON safe)
            'expected' => $expected,
            'actual' => $actual,
            'diff' => $diff,
            'mismatches' => $mismatches,
        ];
    }
}
