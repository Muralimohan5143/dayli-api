<?php

namespace App\Services;

use App\Models\{DraftOrder, Order, OrderLineItem, SubChangeRequest};
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OrderMaterializer
{
    public function materializeForDate($date): void
    {
        $D = $date instanceof Carbon ? $date->copy()->startOfDay() : Carbon::parse($date)->startOfDay();

        // 1) Pull all active CRs on D
        $activeCrs = SubChangeRequest::approved()->activeOn($D)->get();

        foreach ($activeCrs as $cr) {
            /** @var DraftOrder|null $draft */
            $draft = $cr->draftOrder()->with('items')->first();
            if (!$draft) continue; // no template → nothing to generate

            DB::transaction(function () use ($cr, $draft, $D) {
                // 2) Upsert Order (idempotent via unique(customer_id, service_date))
                $order = Order::firstOrCreate(
                    ['customer_id' => $cr->for_user_id, 'service_date' => $D->toDateString()],
                    [
                        'name'               => 'SUB-' . $cr->id . '-' . $D->format('Ymd'),
                        'change_request_id'  => $cr->id,
                        'currency'           => 'INR',
                        'status'             => 'open',
                    ]
                );

                // 3) Upsert items from draft
                foreach ($draft->items as $di) {

                    // app/Services/OrderMaterializer.php (inside the foreach $draft->items loop)
                    $variantId = $di->variant_id;
                    $variant   = $di->variant()->first(); // or eager-load items.variant
                    $vendorId  = $di->vendor_id ?? $this->resolveVendorId($cr, $di->product_id, $variantId);
                    $price     = $this->resolvePrice($cr, $di->product_id, $variantId, $vendorId, $D)
                        ?? $variant?->price
                        ?? $di->price_snapshot
                        ?? 0;

                    OrderLineItem::updateOrCreate(
                        ['order_id' => $order->id, 'variant_id' => $variantId, 'vendor_id' => $vendorId],
                        ['product_id' => $di->product_id, 'qty' => $di->qty, 'unit' => $di->unit, 'price_applied' => $price, 'meta' => $di->meta]
                    );

                    // $vendorId = $di->vendor_id ?? $this->resolveVendorId($cr, $di->product_id);
                    // $price    = $this->resolvePrice($cr, $di->product_id, $vendorId, $D) ?? $di->price_snapshot ?? 0;

                    // OrderLineItem::updateOrCreate(
                    //     [
                    //         'order_id'   => $order->id,
                    //         'product_id' => $di->product_id,
                    //         'vendor_id'  => $vendorId,
                    //     ],
                    //     [
                    //         'qty'           => $di->qty,
                    //         'unit'          => $di->unit,
                    //         'price_applied' => $price,
                    //         'meta'          => $di->meta,
                    //     ]
                    // );
                }

                $order->load('items');
                $order->recalcTotals();
            });
        }
    }

    // TODO: plug your real logic (zone/vendor maps, pricing tables, etc.)
    protected function resolveVendorId(SubChangeRequest $cr, int $productId, int $variantId): ?int
    {
        return null;
    }

    protected function resolvePrice(
        SubChangeRequest $cr,
        int $productId,
        int $variantId,
        ?int $vendorId,
        Carbon $date
    ): ?float {
        return null;
    }
}
