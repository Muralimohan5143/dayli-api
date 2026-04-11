<?php

namespace App\Ops\Handlers;

use App\Models\OutboxReport;
use App\Ops\Contracts\EventHandler;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\FcmService;

class MonthlyInvoiceSendHandler implements EventHandler
{
    public function handle($event): array
    {
        $payload = is_array($event->payload)
            ? $event->payload
            : json_decode($event->payload, true);

        $reportId = (int) ($payload['report_id'] ?? 0);

        if ($reportId <= 0) {
            return [
                'ok' => false,
                'message' => 'Invalid report_id',
            ];
        }

        $report = OutboxReport::findOrFail($reportId);

        $zoneId = (int) data_get($report->payload_json, 'zone_id');
        $subscriptionTypeId = (int) $report->subscription_type_id;
        $start = Carbon::parse($report->start_date)->toDateString();
        $end = Carbon::parse($report->end_date)->toDateString();

        $customers = DB::table('invoices as inv')
            ->join('orders as o', 'o.id', '=', 'inv.order_id')
            ->leftJoin('users as u', 'u.id', '=', 'inv.user_id')
            ->select(
                'inv.user_id as customer_id',
                'u.phone',
                DB::raw("COALESCE(u.display_name, u.name, CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))) as customer_name"),
                DB::raw('SUM(inv.subtotal) as subtotal'),
                DB::raw('SUM(inv.delivery_fee) as delivery_fee'),
                DB::raw('SUM(inv.total) as total_amount'),
                DB::raw('SUM(inv.grand_total) as grand_total')
            )
            ->where('o.zone_id', $zoneId)
            ->whereDate('inv.order_start_date', '>=', $start)
            ->whereDate('inv.order_end_date', '<=', $end)
            ->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(inv.meta, '$.subscription_type_id')) = ?",
                [(string) $subscriptionTypeId]
            )
            ->groupBy(
                'inv.user_id',
                'u.phone',
                'u.display_name',
                'u.name',
                'u.first_name',
                'u.last_name'
            )
            ->get();

        if ($customers->isEmpty()) {
            return [
                'ok' => false,
                'message' => 'No customers found for this report.',
                'report_id' => $report->id,
            ];
        }

        $sentCount = 0;
        $skippedCount = 0;
        $failedCount = 0;

        foreach ($customers as $customer) {
            $customerId = (int) ($customer->customer_id ?? 0);

            if ($customerId <= 0) {
                $skippedCount++;
                continue;
            }

            try {
                $monthLabel = Carbon::parse($start)->format('M-Y');
                $amountText = number_format((float) $customer->total_amount, 2);

                $payload = [
                    'title' => 'Invoice Available',
                    'body'  => "Your invoice for {$monthLabel} is available.\nAmount: Rs {$amountText}/-",
                    'data'  => [
                        'type' => 'invoice_ready',
                        'screen' => 'sub_invoices',
                        'report_id' => (string) $report->id,
                        'customer_id' => (string) $customerId,
                        'subscription_type_id' => (string) $subscriptionTypeId,
                        'zone_id' => (string) $zoneId,
                        'start_date' => $start,
                        'end_date' => $end,
                    ],
                ];

                $pushResult = app(FcmService::class)->sendToUser($customerId, $payload);

                if (($pushResult['total'] ?? 0) === 0) {
                    $skippedCount++;

                    Log::info('Monthly invoice push skipped - no valid FCM token', [
                        'report_id' => $report->id,
                        'customer_id' => $customerId,
                        'customer_name' => $customer->customer_name,
                        'start_date' => $start,
                        'end_date' => $end,
                    ]);

                    continue;
                }

                if (($pushResult['ok'] ?? 0) > 0) {
                    $sentCount++;

                    Log::info('Monthly invoice push sent', [
                        'report_id' => $report->id,
                        'customer_id' => $customerId,
                        'customer_name' => $customer->customer_name,
                        'phone' => $customer->phone,
                        'subtotal' => (float) $customer->subtotal,
                        'delivery_fee' => (float) $customer->delivery_fee,
                        'total_amount' => (float) $customer->total_amount,
                        'grand_total' => (float) $customer->grand_total,
                        'start_date' => $start,
                        'end_date' => $end,
                        'push_result' => $pushResult,
                    ]);
                } else {
                    $failedCount++;

                    Log::error('Monthly invoice push failed', [
                        'report_id' => $report->id,
                        'customer_id' => $customerId,
                        'customer_name' => $customer->customer_name,
                        'phone' => $customer->phone,
                        'start_date' => $start,
                        'end_date' => $end,
                        'push_result' => $pushResult,
                    ]);
                }
            } catch (\Throwable $e) {
                $failedCount++;

                Log::error('Monthly invoice notification failed', [
                    'report_id' => $report->id,
                    'customer_id' => $customerId,
                    'phone' => $customer->phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $report->update([
            'processed_at' => now(),
            'payload_json' => array_merge($report->payload_json ?? [], [
                'send_total_customers' => $customers->count(),
                'send_sent_count' => $sentCount,
                'send_skipped_count' => $skippedCount,
                'send_failed_count' => $failedCount,
                'sent_at' => now()->toDateTimeString(),
            ]),
        ]);

        return [
            'ok' => true,
            'report_id' => $report->id,
            'total_customers' => $customers->count(),
            'sent_count' => $sentCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
        ];
    }
}
