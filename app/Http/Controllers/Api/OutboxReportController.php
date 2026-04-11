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

        $rows = $query->get()->map(function ($report) {
            $subscriptionTypeName = DB::table('subscription_types')
                ->where('id', $report->subscription_type_id)
                ->value('name');

            $arr = $report->toArray();
            $arr['subscription_type_name'] = $subscriptionTypeName;

            return $arr;
        });

        return response()->json([
            'ok' => true,
            'data' => $rows,
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
        $subscriptionTypeName = DB::table('subscription_types')
            ->where('id', $subscriptionTypeId)
            ->value('name');
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
            'report' => array_merge($report->toArray(), [
                'subscription_type_name' => $subscriptionTypeName,
            ]),
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
        // $subscriptionTypeName = DB::table('subscription_types')
        //     ->where('id', $subscriptionTypeId)
        //     ->value('name');
        $start = Carbon::parse($report->start_date)->toDateString();
        $endExclusive = Carbon::parse($report->end_date)->addDay()->toDateString();

        $report->update([
            'status' => 'processing',
            'processed_at' => now(),
        ]);

        DB::table('outbox_events')->insert([
            'event_type'     => 'report.invoice.generate',
            'aggregate_type' => 'outbox_report',
            'aggregate_id'   => $report->id,
            'payload'        => json_encode([
                'report_id' => $report->id,
                'zone_id' => $zoneId,
                'subscription_type_id' => $subscriptionTypeId,
                'start_date' => $start,
                'end_date_exclusive' => $endExclusive,
                'auto_send' => $request->boolean('auto_send'), // ✅ ADD THIS LINE
            ]),
            'status'         => 'pending',
            'priority'       => 1,
            'attempts'       => 0,
            'max_attempts'   => 3,
            'scheduled_at'   => now(),
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Invoice generation queued. Processing will happen shortly.',
            'report' => $report->fresh(),
        ]);
    }

    public function send(Request $request, int $id)
    {
        $user = $request->user();

        $report = OutboxReport::query()
            ->where('zone_manager_id', $user->id)
            ->findOrFail($id);

        $zoneId = (int) data_get($report->payload_json, 'zone_id');
        $subscriptionTypeId = (int) $report->subscription_type_id;
        $start = Carbon::parse($report->start_date)->toDateString();
        $end = Carbon::parse($report->end_date)->toDateString();

        $customers = DB::table('order_items as oi')
            ->join('orders as o', 'o.id', '=', 'oi.order_id')
            ->join('variants as v', 'v.variant_id', '=', 'oi.variant_id')
            ->join('products as p', 'p.product_id', '=', 'v.product_id')
            ->join('subscription_sub_types as sst', 'sst.slug', '=', 'p.product_sub_type')
            ->leftJoin('users as u', 'u.id', '=', 'o.customer_id')
            ->select(
                'o.customer_id',
                'u.phone',
                DB::raw("COALESCE(u.display_name, u.name, CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as customer_name"),
                DB::raw('SUM(oi.line_total) as subtotal')
            )
            ->where('o.zone_id', $zoneId)
            ->where('sst.subscription_type_id', $subscriptionTypeId)
            ->whereBetween('oi.actuals_date', [$start, $end])
            ->groupBy(
                'o.customer_id',
                'u.phone',
                'u.display_name',
                'u.name',
                'u.first_name',
                'u.last_name'
            )
            ->get();

        if ($customers->isEmpty()) {
            return response()->json([
                'ok' => false,
                'message' => 'No customers found for this report.',
            ], 422);
        }

        $queued = 0;

        foreach ($customers as $customer) {
            if (empty($customer->phone)) {
                continue;
            }

            DB::table('outbox_events')->insert([
                'event_type'     => 'report.invoice.send',
                'aggregate_type' => 'outbox_report',
                'aggregate_id'   => $report->id,
                'payload'        => json_encode([
                    'report_id' => $report->id,
                    'customer_id' => (int) $customer->customer_id,
                    'customer_name' => $customer->customer_name,
                    'phone' => $customer->phone,
                    'zone_id' => $zoneId,
                    'subscription_type_id' => $subscriptionTypeId,
                    'start_date' => $start,
                    'end_date' => $end,
                ]),
                'status'         => 'pending',
                'priority'       => 1,
                'attempts'       => 0,
                'max_attempts'   => 3,
                'scheduled_at'   => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);

            $queued++;
        }

        $report->update([
            'processed_at' => now(),
        ]);

        return response()->json([
            'ok' => true,
            'message' => "Invoice sending queued for {$queued} customers.",
            'count' => $queued,
            'report' => $report->fresh(),
        ]);
    }
    public function myInvoices(Request $request)
    {
        $user = $request->user();

        $start = $request->query('start_date');
        $end = $request->query('end_date');
        $subscriptionTypeId = (int) $request->query('subscription_type_id', 0);

        $query = DB::table('invoices as i')
            ->join('orders as o', 'o.id', '=', 'i.order_id')
            ->select(
                'i.id',
                'i.order_id',
                'i.user_id',
                'i.order_type',
                'i.order_start_date',
                'i.order_end_date',
                'i.billing_name',
                'i.invoice_number',
                'i.invoice_date',
                'i.status',
                'i.payment_status',
                'i.subtotal',
                'i.delivery_fee',
                'i.total',
                'i.grand_total',
                'i.created_at'
            )
            ->where('i.user_id', $user->id);

        if (!empty($start)) {
            $query->whereDate('i.order_start_date', '>=', $start);
        }

        if (!empty($end)) {
            $query->whereDate('i.order_end_date', '<=', $end);
        }

        if ($subscriptionTypeId > 0) {
            $query
                ->join('order_items as oi', 'oi.order_id', '=', 'o.id')
                ->join('variants as v', 'v.variant_id', '=', 'oi.variant_id')
                ->join('products as p', 'p.product_id', '=', 'v.product_id')
                ->join('subscription_sub_types as sst', 'sst.slug', '=', 'p.product_sub_type')
                ->where('sst.subscription_type_id', $subscriptionTypeId)
                ->groupBy(
                    'i.id',
                    'i.order_id',
                    'i.user_id',
                    'i.order_type',
                    'i.order_start_date',
                    'i.order_end_date',
                    'i.billing_name',
                    'i.invoice_number',
                    'i.invoice_date',
                    'i.status',
                    'i.payment_status',
                    'i.subtotal',
                    'i.delivery_fee',
                    'i.total',
                    'i.grand_total',
                    'i.created_at'
                );
        }

        $rows = $query
            ->orderByDesc('i.invoice_date')
            ->orderByDesc('i.id')
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $rows,
        ]);
    }
}
