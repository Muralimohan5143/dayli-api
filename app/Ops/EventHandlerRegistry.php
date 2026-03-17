<?php

namespace App\Ops;

use App\Ops\Contracts\EventHandler;
use InvalidArgumentException;

class EventHandlerRegistry
{
    /** @var array<string, class-string<EventHandler>> */
    private array $map;

    public function __construct()
    {
        $this->map = [
            // reconciliation
            'vendor_supply_entered' => \App\Ops\Handlers\VendorSupplyReconcileHandler::class,
            'zone.daily.reconcile'  => \App\Ops\Handlers\DailyZoneReconcileHandler::class,
            'recon.daily_zone'      => \App\Ops\Handlers\DailyZoneReconcileHandler::class,
            'daily_zone_reconcile'  => \App\Ops\Handlers\DailyZoneReconcileHandler::class,

            // shopify
            'sync.shopify.customer_zonecode_push' => \App\Ops\Handlers\ShopifyZonecodePushHandler::class,
        ];
    }

    public function resolve(string $eventType): EventHandler
    {
        $cls = $this->map[$eventType] ?? null;

        if (!$cls) {
            throw new InvalidArgumentException("No handler registered for event_type={$eventType}");
        }

        return app($cls);
    }
}
