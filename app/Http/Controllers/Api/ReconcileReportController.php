<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\ProcurementDeliveryException;

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

        $rows = json_decode($row->mismatches_json, true);

        return response()->json([
            'data' => [
                'report' => $row,
                'summary' => json_decode($row->summary_json, true),
                'rows' => $rows, // 👈 ALL products now
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
            'exception_type' => 'required|string',
            'direction' => 'required|in:in,out',
            'qty' => 'required|numeric|min:0.01',
            'reason_code' => 'nullable|string',
            'discussion' => 'nullable|string',
        ]);

        $data['status'] = 'approved';
        $data['created_by'] = $request->user()->id;

        ProcurementDeliveryException::create($data);

        // 🔥 trigger reconcile again
        DB::table('outbox_events')->insert([
            'event_type' => 'zone.daily.reconcile',
            'aggregate_type' => 'zone',
            'aggregate_id' => $data['zone_id'],
            'idempotency_key' => 'retrigger:' . uniqid(),
            'payload' => json_encode([
                'zone_id' => $data['zone_id'],
                'delivery_date' => $data['delivery_date'],
                'subscription_type_id' => $data['subscription_type_id'],
            ]),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 5,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }
}
