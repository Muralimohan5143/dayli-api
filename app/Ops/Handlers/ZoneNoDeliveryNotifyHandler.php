<?php

namespace App\Ops\Handlers;

use App\Models\SubChangeRequest;
use App\Services\InteraktService;
use App\Ops\Contracts\EventHandler;

class ZoneNoDeliveryNotifyHandler implements EventHandler
{
    public function handle($event): array
    {
        $payload = $event->payload;

        $zoneId = $payload['zone_id'];
        $typeId = $payload['subscription_type_id'];
        $date = $payload['delivery_date'];
        $reason = $payload['reason'];

        $sent = 0;

        $customers = SubChangeRequest::where('subscription_type_id', $typeId)
            ->where('party_type', 'consumer')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(function ($user) use ($zoneId) {
                return $user && $user->zone_id == $zoneId;
            })
            ->unique('id');

        $isTest = !config('services.interakt_dayli.enabled');

        foreach ($customers as $user) {

            if (!$user || !$user->phone) continue;

            app(InteraktService::class)->sendNoDeliveryAlert(
                $user->phone,
                $payload
            );

            $sent++;

            // ✅ STOP after 1 send in test mode
            if ($isTest) {
                break;
            }
        }

        return [
            'ok' => true,
            'sent' => $sent,
        ];
    }
}
