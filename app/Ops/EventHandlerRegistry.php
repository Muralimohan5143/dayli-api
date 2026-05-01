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

            'reconciliation.matched' => \App\Ops\Handlers\ReconciliationResultNotificationHandler::class,
            'reconciliation.mismatch' => \App\Ops\Handlers\ReconciliationResultNotificationHandler::class,
            // reconciliation
            'vendor_supply_entered' => \App\Ops\Handlers\DailyZoneReconcileHandler::class,
            'agent_delivered_entered' => \App\Ops\Handlers\DailyZoneReconcileHandler::class,

            'zone.daily.reconcile'  => \App\Ops\Handlers\DailyZoneReconcileHandler::class,
            'recon.daily_zone'      => \App\Ops\Handlers\DailyZoneReconcileHandler::class,
            'daily_zone_reconcile'  => \App\Ops\Handlers\DailyZoneReconcileHandler::class,

            // shopify
            'sync.shopify.customer_zonecode_push' => \App\Ops\Handlers\ShopifyZonecodePushHandler::class,

            // reports
            'report.invoice.generate' => \App\Ops\Handlers\MonthlyInvoiceGenerateHandler::class,

            'report.invoice.send_notifications' => \App\Ops\Handlers\MonthlyInvoiceSendHandler::class,


            'zone.no_delivery.notify' => \App\Ops\Handlers\ZoneNoDeliveryNotifyHandler::class,
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
