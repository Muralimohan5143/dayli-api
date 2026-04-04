<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OutboxReport;
use App\Services\InvoiceGeneratorService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutboxReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = OutboxReport::query()
            ->where('zone_manager_id', $user->id)
            ->where('report_type', 'monthly_invoice')
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        return response()->json([
            'ok' => true,
            'data' => $query->get(),
        ]);
    }

    public function show(Request $request, int $id)
    {
        $user = $request->user();

        $report = OutboxReport::query()
            ->where('zone_manager_id', $user->id)
            ->findOrFail($id);

        $zoneId = (int) data_get($report->payload_json, 'zone_id');
        $subscriptionTypeId = (int) $report->subscription_type_id;
        $start = Carbon::parse($report->start_date)->toDateString();
        $end = Carbon::parse($report->end_date)->toDateString();

        $rows = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('variants as v', 'v.variant_id', '=', 'oi.variant_id')
            ->join('products as p', 'p.product_id', '=', 'v.product_id')
            ->join('subscription_sub_types as sst', 'sst.slug', '=', 'p.product_sub_type')
            ->leftJoin('users as u', 'u.id', '=', 'o.customer_id')
            ->select(
                'o.customer_id',
                DB::raw("COALESCE(u.display_name, u.name, CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as customer_name"),
                'u.phone',
                'oi.title',
                'oi.variant_id',
                'oi.product_id',
                DB::raw('SUM(oi.quantity) as total_qty'),
                DB::raw('AVG(oi.unit_price) as avg_unit_price'),
                DB::raw('SUM(oi.line_total) as total_amount'),
                DB::raw('COUNT(DISTINCT o.id) as order_count')
            )
            ->where('o.zone_id', $zoneId)
            ->where('sst.subscription_type_id', $subscriptionTypeId)
            ->whereBetween('oi.actuals_date', [$start, $end])
            ->groupBy(
                'o.customer_id',
                'u.display_name',
                'u.name',
                'u.first_name',
                'u.last_name',
                'u.phone',
                'oi.title',
                'oi.variant_id',
                'oi.product_id'
            )
            ->orderBy('customer_name')
            ->orderBy('oi.title')
            ->get();

        $grouped = $rows->groupBy('customer_id')->map(function ($items, $customerId) use ($zoneId, $start, $end) {
            $first = $items->first();

            $orderCount = (int) DB::table('orders')
                ->where('customer_id', $customerId)
                ->where('zone_id', $zoneId)
                ->whereBetween('delivery_date', [$start, $end])
                ->count();
            $subtotal = round((float) $items->sum('total_amount'), 2);

            $deliveryDays = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->where('o.customer_id', $customerId)
                ->whereBetween('oi.actuals_date', [$start, $end])
                ->distinct('oi.actuals_date')
                ->count('oi.actuals_date');

            $deliveryFeePerDay = 2; // or your config

            $deliveryFee = $deliveryDays * $deliveryFeePerDay;
            $grandTotal = round($subtotal + $deliveryFee, 2);

            return [
                'customer_id' => (int) $customerId,
                'customer_name' => $first->customer_name ?: 'Customer',
                'phone' => $first->phone,
                'order_count' => $orderCount,
                'items' => $items->map(function ($row) {
                    return [
                        'title' => $row->title,
                        'product_id' => $row->product_id,
                        'variant_id' => $row->variant_id,
                        'qty' => (float) $row->total_qty,
                        'unit_price' => round((float) $row->avg_unit_price, 2),
                        'line_total' => round((float) $row->total_amount, 2),
                    ];
                })->values(),
                'subtotal' => $subtotal,
                'delivery_fee' => $deliveryFee,
                'grand_total' => $grandTotal,
            ];
        })->values();

        return response()->json([
            'ok' => true,
            'report' => $report,
            'customers' => $grouped,
        ]);
    }

    public function generate(Request $request, int $id, InvoiceGeneratorService $service)
    {
        $user = $request->user();

        $report = OutboxReport::query()
            ->where('zone_manager_id', $user->id)
            ->findOrFail($id);

        $zoneId = (int) data_get($report->payload_json, 'zone_id');
        $subscriptionTypeId = (int) $report->subscription_type_id;
        $start = Carbon::parse($report->start_date)->toDateString();
        $endExclusive = Carbon::parse($report->end_date)->addDay()->toDateString();

        $report->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);

        $count = $service->generateForReport(
            zoneId: $zoneId,
            subscriptionTypeId: $subscriptionTypeId,
            monthStart: $start,
            monthEndExclusive: $endExclusive,
        );

        $report->update([
            'status' => 'generated',
            'generated_at' => now(),
            'payload_json' => array_merge($report->payload_json ?? [], [
                'generated_invoice_count' => $count,
            ]),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Invoices generated successfully.',
            'count' => $count,
            'report' => $report->fresh(),
        ]);
    }
}
