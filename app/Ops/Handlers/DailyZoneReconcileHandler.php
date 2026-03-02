<?php

namespace App\Ops\Handlers;

use App\Models\OutboxEvent;
use App\Ops\Contracts\EventHandler;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * DailyZoneReconcileHandler
 *
 * Purpose:
 *  - IN  (SUPPLIED)  = supplier orders' order_items.quantity (party_type='supplier')
 *  - OUT (DELIVERED) = consumer orders' order_items.quantity (party_type='consumer')
 *
 * Diff:
 *  - diff = supplied - delivered
 *    > 0 => leftover / extra stock
 *    < 0 => delivered more than supplied (missing supply entries / wrong data)
 *
 * Payload accepted:
 *  - zone_id (required)
 *  - delivery_date | date | deliveryDate (optional, defaults today)
 *  - subscription_type_id (optional; only applied if column exists on SCR)
 *  - delivered_only (optional bool, default true)
 *      If true: consumer side counts only orders with o.delivery_status='delivered'
 *      If false: counts all consumer orders on that date.
 */
class DailyZoneReconcileHandler implements EventHandler
{
    public function handle(OutboxEvent $event): array
    {
        $payload = $event->payload ?? [];

        $zoneId = (int) ($payload['zone_id'] ?? 0);
        if ($zoneId <= 0) {
            throw new \RuntimeException('Invalid payload: zone_id required');
        }

        $dateStr = (string) ($payload['delivery_date']
            ?? $payload['deliveryDate']
            ?? $payload['date']
            ?? Carbon::today()->toDateString());

        $targetDate = Carbon::parse($dateStr)->toDateString();

        $subTypeId = (int) ($payload['subscription_type_id'] ?? $payload['subscriptionTypeId'] ?? 0);

        // Default true (recommended) to avoid counting pre-created consumer orders as delivered.
        $deliveredOnly = array_key_exists('delivered_only', $payload)
            ? (bool) $payload['delivered_only']
            : true;

        // ----------------------------------------
        // SUPPLIED (IN): party_type='supplier'
        // ----------------------------------------
        $suppliedQ = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->join('draft_orders as d', 'd.id', '=', 'o.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'd.change_request_id')
            ->where('o.zone_id', $zoneId)
            ->whereDate('o.delivery_date', $targetDate)
            ->whereNotIn('o.status', ['cancelled'])
            ->where('scr.party_type', 'supplier')
            ->select('oi.product_id', DB::raw('MAX(oi.title) as title'), DB::raw('SUM(oi.quantity) as qty'))
            ->groupBy('oi.product_id');

        // Apply subtype only if SCR has it (your table does)
        if ($subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('sub_change_requests', 'subscription_type_id')) {
            $suppliedQ->where('scr.subscription_type_id', $subTypeId);
        }

        $suppliedRows = $suppliedQ->get();

        $supplied = [];
        $titles   = [];
        $suppliedTotal = 0.0;

        foreach ($suppliedRows as $r) {
            $pid = (string) ($r->product_id ?? 0);
            if ($pid === '0') continue;
            $qty = (float) ($r->qty ?? 0);
            $supplied[$pid] = $qty;
            $suppliedTotal += $qty;

            $t = (string) ($r->title ?? '');
            if ($t !== '') $titles[$pid] = $t;
        }

        // ----------------------------------------
        // DELIVERED (OUT): party_type='consumer'
        // ----------------------------------------
        $deliveredQ = DB::table('orders as o')
            ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
            ->join('draft_orders as d', 'd.id', '=', 'o.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'd.change_request_id')
            ->where('o.zone_id', $zoneId)
            ->whereDate('o.delivery_date', $targetDate)
            ->whereNotIn('o.status', ['cancelled'])
            ->where('scr.party_type', 'consumer')
            ->select('oi.product_id', DB::raw('MAX(oi.title) as title'), DB::raw('SUM(oi.quantity) as qty'))
            ->groupBy('oi.product_id');

        if ($subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('sub_change_requests', 'subscription_type_id')) {
            $deliveredQ->where('scr.subscription_type_id', $subTypeId);
        }

        // If consumer orders exist before delivery, this filter prevents false delivered.
        if ($deliveredOnly && DB::getSchemaBuilder()->hasColumn('orders', 'delivery_status')) {
            $deliveredQ->where('o.delivery_status', 'delivered');
        }

        $deliveredRows = $deliveredQ->get();

        $delivered = [];
        $deliveredTotal = 0.0;

        foreach ($deliveredRows as $r) {
            $pid = (string) ($r->product_id ?? 0);
            if ($pid === '0') continue;
            $qty = (float) ($r->qty ?? 0);
            $delivered[$pid] = $qty;
            $deliveredTotal += $qty;

            $t = (string) ($r->title ?? '');
            if ($t !== '' && !isset($titles[$pid])) $titles[$pid] = $t;
        }

        // ----------------------------------------
        // DIFF = supplied - delivered
        // ----------------------------------------
        $keys = array_values(array_unique(array_merge(array_keys($supplied), array_keys($delivered))));

        $diff = [];
        $mismatches = [];

        foreach ($keys as $pid) {
            $in  = (float) ($supplied[$pid] ?? 0);
            $out = (float) ($delivered[$pid] ?? 0);
            $d   = $in - $out;

            $diff[$pid] = $d;

            if (abs($d) > 0.000001) {
                $mismatches[] = [
                    'product_id'    => (int) $pid,
                    'title'         => (string) ($titles[$pid] ?? ''),
                    'supplied_qty'  => $in,
                    'delivered_qty' => $out,
                    'diff_qty'      => $d,
                ];
            }
        }

        $status = count($mismatches) ? 'mismatch' : 'matched';

        // Counts of orders (helpful debug)
        $supplierOrdersCount = (int) DB::table('orders as o')
            ->join('draft_orders as d', 'd.id', '=', 'o.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'd.change_request_id')
            ->where('o.zone_id', $zoneId)
            ->whereDate('o.delivery_date', $targetDate)
            ->whereNotIn('o.status', ['cancelled'])
            ->where('scr.party_type', 'supplier')
            ->when($subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('sub_change_requests', 'subscription_type_id'), function ($q) use ($subTypeId) {
                $q->where('scr.subscription_type_id', $subTypeId);
            })
            ->count();

        $consumerOrdersCountQ = DB::table('orders as o')
            ->join('draft_orders as d', 'd.id', '=', 'o.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'd.change_request_id')
            ->where('o.zone_id', $zoneId)
            ->whereDate('o.delivery_date', $targetDate)
            ->whereNotIn('o.status', ['cancelled'])
            ->where('scr.party_type', 'consumer')
            ->when($subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('sub_change_requests', 'subscription_type_id'), function ($q) use ($subTypeId) {
                $q->where('scr.subscription_type_id', $subTypeId);
            });

        if ($deliveredOnly && DB::getSchemaBuilder()->hasColumn('orders', 'delivery_status')) {
            $consumerOrdersCountQ->where('o.delivery_status', 'delivered');
        }

        $consumerOrdersCount = (int) $consumerOrdersCountQ->count();

        return [
            'ok' => true,
            'handler' => 'DailyZoneReconcileHandler',

            'zone_id' => $zoneId,
            'delivery_date' => $targetDate,
            'subscription_type_id' => $subTypeId > 0 ? $subTypeId : null,
            'delivered_only' => $deliveredOnly,

            'status' => $status,

            'orders' => [
                'supplier_orders' => $supplierOrdersCount,
                'consumer_orders' => $consumerOrdersCount,
            ],

            'totals' => [
                'supplied_qty' => $suppliedTotal,
                'delivered_qty' => $deliveredTotal,
                'diff_qty' => $suppliedTotal - $deliveredTotal,
            ],

            'supplied' => $supplied,
            'delivered' => $delivered,
            'diff' => $diff,
            'mismatches' => $mismatches,
        ];
    }
}
