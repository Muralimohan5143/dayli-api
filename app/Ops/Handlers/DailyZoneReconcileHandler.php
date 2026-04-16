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

        $eventType = $event->event_type ?? '';

        if (in_array($eventType, ['vendor_supply_entered', 'agent_delivered_entered'])) {

            $zoneId = (int) ($payload['zone_id'] ?? 0);
            $date   = (string) ($payload['delivery_date'] ?? '');
            $subTypeId = (int) ($payload['subscription_type_id'] ?? 0);

            if ($zoneId <= 0 || !$date || $subTypeId <= 0) {
                return ['ok' => false, 'reason' => 'invalid payload'];
            }

            // check both events exist
            $vendorExists = DB::table('outbox_events')
                ->where('event_type', 'vendor_supply_entered')
                ->where('idempotency_key', "vendor_supply_entered:zone:{$zoneId}:date:{$date}:subtype:{$subTypeId}")
                ->exists();

            $agentExists = DB::table('outbox_events')
                ->where('event_type', 'agent_delivered_entered')
                ->where('idempotency_key', "agent_delivered_entered:zone:{$zoneId}:date:{$date}:subtype:{$subTypeId}")
                ->exists();

            // ❌ if both not ready → stop
            if (!$vendorExists || !$agentExists) {
                return ['ok' => true, 'skipped' => true, 'reason' => 'waiting for both sides'];
            }

            // ✅ both ready → continue to reconciliation
        }

        $zoneId = (int) ($payload['zone_id'] ?? 0);
        if ($zoneId <= 0) {
            throw new \RuntimeException('Invalid payload: zone_id required');
        }

        $dateStr = (string) (
            $payload['delivery_date']
            ?? $payload['deliveryDate']
            ?? $payload['date']
            ?? Carbon::today()->toDateString()
        );

        $targetDate = Carbon::parse($dateStr)->toDateString();

        $subTypeId = (int) ($payload['subscription_type_id'] ?? $payload['subscriptionTypeId'] ?? 0);

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
            ->select(
                'oi.product_id',
                'oi.variant_id',
                DB::raw('MAX(oi.title) as title'),
                DB::raw('SUM(oi.quantity) as qty')
            )
            ->groupBy('oi.product_id', 'oi.variant_id');

        if ($subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('sub_change_requests', 'subscription_type_id')) {
            $suppliedQ->where('scr.subscription_type_id', $subTypeId);
        }

        $suppliedRows = $suppliedQ->get();

        $supplied = [];
        $titles = [];
        $suppliedTotal = 0.0;

        foreach ($suppliedRows as $r) {
            $productId = (int) ($r->product_id ?? 0);
            $variantId = (int) ($r->variant_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $key = (string)$variantId;
            $qty = (float) ($r->qty ?? 0);

            $supplied[$key] = $qty;
            $suppliedTotal += $qty;

            $title = (string) ($r->title ?? '');
            if ($title !== '') {
                $titles[$key] = $title;
            }
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
            ->select(
                'oi.product_id',
                'oi.variant_id',
                DB::raw('MAX(oi.title) as title'),
                DB::raw('SUM(oi.quantity) as qty')
            )
            ->groupBy('oi.product_id', 'oi.variant_id');

        if ($subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('sub_change_requests', 'subscription_type_id')) {
            $deliveredQ->where('scr.subscription_type_id', $subTypeId);
        }

        if ($deliveredOnly && DB::getSchemaBuilder()->hasColumn('orders', 'delivery_status')) {
            $deliveredQ->where('o.delivery_status', 'delivered');
        }

        $deliveredRows = $deliveredQ->get();

        $delivered = [];
        $deliveredTotal = 0.0;

        foreach ($deliveredRows as $r) {
            $productId = (int) ($r->product_id ?? 0);
            $variantId = (int) ($r->variant_id ?? 0);

            if ($productId <= 0) {
                continue;
            }

            $key = (string) $variantId;
            $qty = (float) ($r->qty ?? 0);

            $delivered[$key] = $qty;
            $deliveredTotal += $qty;

            $title = (string) ($r->title ?? '');
            if ($title !== '' && !isset($titles[$key])) {
                $titles[$key] = $title;
            }
        }


        // ----------------------------------------
        // APPROVED EXCEPTIONS
        // ----------------------------------------
        $exceptionInRows = DB::table('procurement_delivery_exceptions')
            ->where('zone_id', $zoneId)
            ->whereDate('delivery_date', $targetDate)
            ->where('subscription_type_id', $subTypeId)
            ->where('status', 'approved')
            ->where('direction', 'in')
            ->select(
                'variant_id',
                DB::raw('SUM(qty) as qty')
            )
            ->groupBy('variant_id')
            ->get();

        $exceptionOutRows = DB::table('procurement_delivery_exceptions')
            ->where('zone_id', $zoneId)
            ->whereDate('delivery_date', $targetDate)
            ->where('subscription_type_id', $subTypeId)
            ->where('status', 'approved')
            ->where('direction', 'out')
            ->select(
                'variant_id',
                DB::raw('SUM(qty) as qty')
            )
            ->groupBy('variant_id')
            ->get();

        $exceptionIn = [];
        $exceptionOut = [];
        $exceptionInTotal = 0.0;
        $exceptionOutTotal = 0.0;

        foreach ($exceptionInRows as $r) {
            $key = (string) ((int) ($r->variant_id ?? 0));
            if ($key === '0') {
                continue;
            }

            $qty = (float) ($r->qty ?? 0);
            $exceptionIn[$key] = $qty;
            $exceptionInTotal += $qty;
        }

        foreach ($exceptionOutRows as $r) {
            $key = (string) ((int) ($r->variant_id ?? 0));
            if ($key === '0') {
                continue;
            }

            $qty = (float) ($r->qty ?? 0);
            $exceptionOut[$key] = $qty;
            $exceptionOutTotal += $qty;
        }

        // ----------------------------------------
        // FINAL DIFF = (supplied + exception_in - exception_out) - delivered
        // ----------------------------------------
        $keys = array_values(array_unique(array_merge(
            array_keys($supplied),
            array_keys($delivered),
            array_keys($exceptionIn),
            array_keys($exceptionOut)
        )));

        $diff = [];
        $rawDiff = [];
        $mismatches = [];
        $matchedRows = [];

        foreach ($keys as $key) {
            $suppliedQty = (float) ($supplied[$key] ?? 0);
            $deliveredQty = (float) ($delivered[$key] ?? 0);
            $exceptionInQty = (float) ($exceptionIn[$key] ?? 0);
            $exceptionOutQty = (float) ($exceptionOut[$key] ?? 0);

            $adjustedSuppliedQty = $suppliedQty + $exceptionInQty - $exceptionOutQty;

            $raw = $suppliedQty - $deliveredQty;
            $final = $adjustedSuppliedQty - $deliveredQty;

            $rawDiff[$key] = $raw;
            $diff[$key] = $final;

            $variantId = (int) $key;

            $row = [
                'product_id' => 0,
                'variant_id' => $variantId,
                'title' => (string) ($titles[$key] ?? ''),
                'supplied_qty' => $suppliedQty,
                'delivered_qty' => $deliveredQty,
                'exception_in_qty' => $exceptionInQty,
                'exception_out_qty' => $exceptionOutQty,
                'adjusted_supplied_qty' => $adjustedSuppliedQty,
                'raw_diff_qty' => $raw,
                'diff_qty' => $final,
                'final_status' => abs($final) > 0.000001 ? 'mismatch' : 'matched',
            ];

            if (abs($final) > 0.000001) {
                $mismatches[] = $row;
            } else {
                $matchedRows[] = $row;
            }
        }

        if (count($mismatches) > 0) {
            $status = 'mismatch';
        } else {
            // check if any exception used
            $hasApprovedExceptions = ($exceptionInTotal > 0 || $exceptionOutTotal > 0);

            $status = $hasApprovedExceptions ? 'reconciled' : 'matched';
        }

        $allRows = array_merge($matchedRows, $mismatches);

        // 1) Save reconciliation report
        DB::table('reconciliation_reports')->updateOrInsert(
            [
                'zone_id' => $zoneId,
                'delivery_date' => $targetDate,
                'subscription_type_id' => $subTypeId,
            ],
            [
                'status' => $status,
                'summary_json' => json_encode([
                    'supplied_total' => $suppliedTotal,
                    'delivered_total' => $deliveredTotal,
                    'exception_in_total' => $exceptionInTotal,
                    'exception_out_total' => $exceptionOutTotal,
                    'adjusted_supplied_total' => $suppliedTotal + $exceptionInTotal - $exceptionOutTotal,
                    'raw_diff_total' => $suppliedTotal - $deliveredTotal,
                    'diff_total' => ($suppliedTotal + $exceptionInTotal - $exceptionOutTotal) - $deliveredTotal,
                    'matched_count' => count($matchedRows),
                    'mismatch_count' => count($mismatches),
                ]),
                'mismatches_json' => json_encode($allRows),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        // 2) Create result outbox event
        $resultEventType = $status === 'matched'
            ? 'reconciliation.matched'
            : 'reconciliation.mismatch';

        DB::table('outbox_events')->updateOrInsert(
            [
                'idempotency_key' => "recon_result:zone:{$zoneId}:date:{$targetDate}:subtype:{$subTypeId}",
            ],
            [
                'event_type' => $resultEventType,
                'aggregate_type' => 'zone',
                'aggregate_id' => $zoneId,
                'scheduled_at' => now(),
                'payload' => json_encode([
                    'zone_id' => $zoneId,
                    'delivery_date' => $targetDate,
                    'subscription_type_id' => $subTypeId,
                    'status' => $status,
                    'mismatches' => $mismatches,
                    'source' => 'dayli_app',
                ]),
                'status' => 'pending',
                'attempts' => 0,
                'max_attempts' => 10,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );



        $supplierOrdersCount = (int) DB::table('orders as o')
            ->join('draft_orders as d', 'd.id', '=', 'o.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'd.change_request_id')
            ->where('o.zone_id', $zoneId)
            ->whereDate('o.delivery_date', $targetDate)
            ->whereNotIn('o.status', ['cancelled'])
            ->where('scr.party_type', 'supplier')
            ->when(
                $subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('sub_change_requests', 'subscription_type_id'),
                function ($q) use ($subTypeId) {
                    $q->where('scr.subscription_type_id', $subTypeId);
                }
            )
            ->count();

        $consumerOrdersCountQ = DB::table('orders as o')
            ->join('draft_orders as d', 'd.id', '=', 'o.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'd.change_request_id')
            ->where('o.zone_id', $zoneId)
            ->whereDate('o.delivery_date', $targetDate)
            ->whereNotIn('o.status', ['cancelled'])
            ->where('scr.party_type', 'consumer')
            ->when(
                $subTypeId > 0 && DB::getSchemaBuilder()->hasColumn('sub_change_requests', 'subscription_type_id'),
                function ($q) use ($subTypeId) {
                    $q->where('scr.subscription_type_id', $subTypeId);
                }
            );

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
                'exception_in_qty' => $exceptionInTotal,
                'exception_out_qty' => $exceptionOutTotal,
                'adjusted_supplied_qty' => $suppliedTotal + $exceptionInTotal - $exceptionOutTotal,
                'raw_diff_qty' => $suppliedTotal - $deliveredTotal,
                'diff_qty' => ($suppliedTotal + $exceptionInTotal - $exceptionOutTotal) - $deliveredTotal,
            ],
            'supplied' => $supplied,
            'delivered' => $delivered,
            'diff' => $diff,
            'mismatches' => $mismatches,
            'raw_diff' => $rawDiff,
            'matched_rows' => $matchedRows,
        ];
    }
}
