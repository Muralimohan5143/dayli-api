<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ProcurementDeliveryException;
use App\Ops\Handlers\DailyZoneReconcileHandler;

class ReconcileReportController extends Controller
{
    // 1. List reports
    public function index(Request $request)
    {
        $zoneId = $request->user()->zone_id;

        $rows = DB::table('reconciliation_reports')
            ->where('zone_id', $zoneId)
            ->orderByDesc('delivery_date')
            ->limit(30)
            ->get();

        return response()->json(['data' => $rows]);
    }

    // 2. Show report details
    public function show($id)
    {
        $row = DB::table('reconciliation_reports')->where('id', $id)->first();

        if (!$row) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $rows = json_decode($row->mismatches_json, true) ?? [];

        $exceptions = DB::table('procurement_delivery_exceptions')
            ->where('zone_id', $row->zone_id)
            ->whereDate('delivery_date', $row->delivery_date)
            ->where('subscription_type_id', $row->subscription_type_id)
            ->where('status', 'approved')
            ->orderBy('id')
            ->get()
            ->groupBy('variant_id');

        foreach ($rows as &$r) {
            $variantId = (string) ($r['variant_id'] ?? '');
            $items = $exceptions->get((int) $variantId, collect());

            $labels = [];
            $discussions = [];

            foreach ($items as $ex) {
                $qty = (float) ($ex->qty ?? 0);
                $qtyText = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');

                if ($ex->exception_type === 'procurement' && $ex->direction === 'in') {
                    $labels[] = $qtyText . ' purchased';
                } elseif ($ex->exception_type === 'loss' && $ex->direction === 'out') {
                    $labels[] = $qtyText . ' loss';
                } elseif ($ex->exception_type === 'adhoc' && $ex->direction === 'out') {
                    $labels[] = $qtyText . ' new';
                }

                if (!empty($ex->discussion)) {
                    $discussions[] = $ex->discussion;
                }
            }

            $r['correction_label'] = implode(', ', $labels);
            $r['correction_discussion'] = implode(' | ', $discussions);
        }
        unset($r);

        return response()->json([
            'data' => [
                'report' => $row,
                'summary' => json_decode($row->summary_json, true),
                'rows' => $rows,
            ]
        ]);
    }

    // 3. Save exception
    public function storeException(Request $request)
    {
        $data = $request->validate([
            'zone_id' => 'required|integer',
            'delivery_date' => 'required|date',
            'subscription_type_id' => 'required|integer',
            'variant_id' => 'required|integer',
            'exception_type' => 'required|string|in:procurement,adhoc,loss',
            'direction' => 'required|in:in,out',
            'qty' => 'required|numeric|min:0.01',
            'reason_code' => 'nullable|string',
            'discussion' => 'nullable|string',

            'customer_name' => 'nullable|string',
            'customer_phone' => 'nullable|string',
            'shop_name' => 'nullable|string',
            'shop_phone' => 'nullable|string',
            'locality' => 'nullable|string',
        ]);

        $data['status'] = 'approved';
        $data['created_by'] = $request->user()->id;

        ProcurementDeliveryException::updateOrCreate(
            [
                'zone_id' => $data['zone_id'],
                'delivery_date' => $data['delivery_date'],
                'subscription_type_id' => $data['subscription_type_id'],
                'variant_id' => $data['variant_id'],
                'exception_type' => $data['exception_type'],
                'direction' => $data['direction'],
                'reason_code' => $data['reason_code'],
            ],
            [
                'qty' => $data['qty'],
                'discussion' => $data['discussion'] ?? null,
                'status' => 'approved',
                'created_by' => $request->user()->id,
                'meta_json' => $data['meta_json'] ?? null,
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
            ]
        );

        $data['meta_json'] = [
            'customer_name' => $request->input('customer_name'),
            'customer_phone' => $request->input('customer_phone'),
            'shop_name' => $request->input('shop_name'),
            'shop_phone' => $request->input('shop_phone'),
            'locality' => $request->input('locality'),
        ];

        // 🔥 trigger reconcile again
        // DB::table('outbox_events')->insert([
        //     'event_type' => 'zone.daily.reconcile',
        //     'aggregate_type' => 'zone',
        //     'aggregate_id' => $data['zone_id'],
        //     'idempotency_key' => 'retrigger:' . uniqid(),

        //     'scheduled_at' => now(), // 🔥 ADD THIS LINE

        //     'payload' => json_encode([
        //         'zone_id' => $data['zone_id'],
        //         'delivery_date' => $data['delivery_date'],
        //         'subscription_type_id' => $data['subscription_type_id'],
        //     ]),
        //     'status' => 'pending',
        //     'attempts' => 0,
        //     'max_attempts' => 5,
        //     'created_at' => now(),
        //     'updated_at' => now(),
        // ]);

        $fakeEvent = new \App\Models\OutboxEvent([
            'event_type' => 'zone.daily.reconcile',
            'payload' => [
                'zone_id' => $data['zone_id'],
                'delivery_date' => $data['delivery_date'],
                'subscription_type_id' => $data['subscription_type_id'],
            ],
        ]);

        $result = app(DailyZoneReconcileHandler::class)->handle($fakeEvent);

        return response()->json([
            'ok' => true,
            'reconcile_result' => $result,
        ]);
    }
}
