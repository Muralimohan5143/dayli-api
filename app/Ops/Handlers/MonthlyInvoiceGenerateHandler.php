<?php

namespace App\Ops\Handlers;

use App\Models\OutboxEvent;
use App\Models\OutboxReport;
use App\Ops\Contracts\EventHandler;
use App\Services\InvoiceGeneratorService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MonthlyInvoiceGenerateHandler implements EventHandler
{
    public function __construct(
        private InvoiceGeneratorService $service
    ) {}

    public function handle($event): array
    {
        $payload = is_array($event->payload)
            ? $event->payload
            : json_decode($event->payload, true);

        $reportId = (int) ($payload['report_id'] ?? 0);
        $zoneId = (int) ($payload['zone_id'] ?? 0);
        $subscriptionTypeId = (int) ($payload['subscription_type_id'] ?? 0);
        $start = (string) ($payload['start_date'] ?? '');
        $endExclusive = (string) ($payload['end_date_exclusive'] ?? '');
        $autoSend = !empty($payload['auto_send']); // ✅ ADD THIS

        $report = OutboxReport::findOrFail($reportId);

        if ($report->status === 'generated') {
            return [
                'ok' => true,
                'message' => 'Already generated',
                'report_id' => $report->id,
            ];
        }

        $count = $this->service->generateForReport(
            zoneId: $zoneId,
            subscriptionTypeId: $subscriptionTypeId,
            monthStart: $start,
            monthEndExclusive: $endExclusive,
        );

        $report->update([
            'status' => 'generated',
            'generated_at' => now(),
            'processed_at' => now(),
            'payload_json' => array_merge($report->payload_json ?? [], [
                'generated_invoice_count' => $count,
            ]),
        ]);

        // ✅ AUTO SEND TRIGGER
        if ($autoSend) {
            DB::table('outbox_events')->insert([
                'event_type'     => 'report.invoice.send_notifications',
                'aggregate_type' => 'outbox_report',
                'aggregate_id'   => $reportId,
                'payload'        => json_encode([
                    'report_id' => $reportId,
                    'zone_id' => $zoneId,
                    'subscription_type_id' => $subscriptionTypeId,
                    'start_date' => $start,
                    'end_date_exclusive' => $endExclusive,
                ]),
                'status'         => 'pending',
                'priority'       => 1,
                'attempts'       => 0,
                'max_attempts'   => 3,
                'scheduled_at'   => now(),
                'created_at'     => now(),
                'updated_at'     => now(),
            ]);
        }

        return [
            'ok' => true,
            'report_id' => $report->id,
            'count' => $count,
            'start_date' => $start,
            'end_date_exclusive' => $endExclusive,
        ];
    }
}
