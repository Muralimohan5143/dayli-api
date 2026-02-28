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
            'recon.vendor_supply_entered' => \App\Ops\Handlers\VendorSupplyReconcileHandler::class,

            // 🔥 ADD THIS LINE
            'vendor_supply_entered' => \App\Ops\Handlers\VendorSupplyReconcileHandler::class,


            // shopify
            'sync.shopify.customer_zonecode_push' => \App\Ops\Handlers\ShopifyZonecodePushHandler::class,

            // interakt / clickup examples (add later)
            // 'sync.clickup.task_update' => ...
            // 'notify.interakt.whatsapp' => ...
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
