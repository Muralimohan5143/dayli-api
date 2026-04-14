<?php

namespace App\Ops\Handlers;

use App\Models\OutboxEvent;
use App\Ops\Contracts\EventHandler;
use App\Jobs\SendPushToUserJob;
use Illuminate\Support\Facades\DB;

class ReconciliationResultNotificationHandler implements EventHandler
{
    public function handle(OutboxEvent $event): array
    {
        $payload = $event->payload ?? [];

        $zoneId = (int) ($payload['zone_id'] ?? 0);
        $date = (string) ($payload['delivery_date'] ?? '');
        $subTypeId = (int) ($payload['subscription_type_id'] ?? 0);
        $status = (string) ($payload['status'] ?? 'mismatch');
        $mismatches = $payload['mismatches'] ?? [];

        // choose recipients
        // replace this query with your real zone manager / admin recipient logic
        $recipientIds = DB::table('users as u')
            ->join('model_has_roles as mhr', function ($join) {
                $join->on('mhr.model_id', '=', 'u.id')
                    ->where('mhr.model_type', \App\Models\User::class);
            })
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('u.zone_id', $zoneId)
            ->whereIn('r.name', ['admin', 'zone-manager', 'zones-head', 'zones-director'])
            ->distinct()
            ->pluck('u.id')
            ->map(fn($id) => (int) $id)
            ->all();

        $title = $status === 'matched'
            ? "Reconciliation matched"
            : "Reconciliation mismatch";

        $body = $status === 'matched'
            ? "Zone {$zoneId} | {$date} matched"
            : "Zone {$zoneId} | {$date} mismatch found";

        foreach ($recipientIds as $userId) {
            SendPushToUserJob::dispatch($userId, [
                'title' => $title,
                'body' => $body,
                'data' => [
                    'type' => 'reconciliation',
                    'zone_id' => (string) $zoneId,
                    'delivery_date' => $date,
                    'subscription_type_id' => (string) $subTypeId,
                    'status' => $status,
                    'mismatch_count' => (string) count($mismatches),
                    'screen' => 'reconciliation_report',
                ],
            ]);
        }

        return [
            'ok' => true,
            'sent_to' => count($recipientIds),
            'status' => $status,
        ];
    }
}
