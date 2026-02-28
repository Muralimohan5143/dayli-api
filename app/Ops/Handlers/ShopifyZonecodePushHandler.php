<?php

namespace App\Ops\Handlers;

use App\Models\OutboxEvent;
use App\Ops\Contracts\EventHandler;

class ShopifyZonecodePushHandler implements EventHandler
{
    public function handle(OutboxEvent $event): array
    {
        $payload = $event->payload;

        $customerId = (int) ($payload['customer_id'] ?? 0);
        $zoneCode = (string) ($payload['zone_code'] ?? '');

        if ($customerId <= 0 || $zoneCode === '') {
            throw new \RuntimeException("Invalid payload: customer_id/zone_code required");
        }

        // TODO: call Shopify Admin API, set metafield ZoneCode
        // Ensure idempotent: "set to same value" is safe.

        return [
            'ok' => true,
            'customer_id' => $customerId,
            'zone_code' => $zoneCode,
        ];
    }
}
