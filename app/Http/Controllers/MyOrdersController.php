<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;

class MyOrdersController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $userId = (int) $user->id;

        /*
         * Return both:
         *
         * 1. Shopify orders
         * 2. Home Food orders
         *
         * Other order types are not included yet.
         */
        $query = Order::query()
            ->with([
                'items:id,order_id,title,variant,quantity,unit_price,line_total,image_url',
            ])
            ->where('customer_id', $userId)
            ->where(function ($q) {
                $q->where('order_type', 'shopify')
                    ->orWhere(function ($homeFoodQuery) {
                        $homeFoodQuery
                            ->where('order_type', 'on_demand')
                            ->where('source_name', 'dayli_home_food');
                    });
            })
            ->orderByDesc('created_at');

        /*
         * Optional server-side status filter.
         *
         * Your current Flutter screen filters statuses locally,
         * but this remains available for future use.
         */
        $status = $request->query('status');

        if (! empty($status)) {
            $query->where('status', $status);
        }

        $orders = $query->get();

        $output = $orders->map(function (Order $order) {
            /*
             * Order model already casts meta to array.
             */
            $meta = is_array($order->meta)
                ? $order->meta
                : [];

            /*
             * Shopify metafields are not currently cast in Order model,
             * so decode them safely here.
             */
            $metafields = [];

            if (! empty($order->metafields)) {
                if (is_array($order->metafields)) {
                    $metafields = $order->metafields;
                } elseif (is_string($order->metafields)) {
                    $decoded = json_decode($order->metafields, true);

                    if (is_array($decoded)) {
                        $metafields = $decoded;
                    }
                }
            }

            /*
             * Shopify location fields normally come from metafields.
             * Home Food can use values stored in meta.
             */
            $meta['nagar'] =
                $metafields['nagar']
                ?? $meta['nagar']
                ?? 'My Area';

            $meta['address'] =
                $metafields['address']
                ?? $meta['address']
                ?? '';

            /*
             * Flutter OrderModel currently reads items from meta['items'].
             * Add real order items here for both Shopify and Home Food.
             */
            $meta['items'] = $order->items
                ->map(function ($item) {
                    return [
                        'title' => $item->title ?: 'Item',
                        'variant' => $item->variant,
                        'quantity' => (int) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'line_total' => (float) $item->line_total,
                        'image_url' => $item->image_url,
                    ];
                })
                ->values()
                ->all();

            /*
             * Simple category for Flutter.
             */
            $orderCategory =
                $order->order_type === 'shopify'
                ? 'shop'
                : 'home_food';

            /*
             * Shopify has shopify_name.
             * Home Food normally does not have a number yet.
             */
            $displayNumber =
                $order->shopify_name
                ?: $order->number
                ?: (
                    $orderCategory === 'home_food'
                    ? 'HF-' . $order->id
                    : 'ORD-' . $order->id
                );

            return [
                'id' => (int) $order->id,
                'number' => $displayNumber,

                'order_type' => $order->order_type,
                'source_name' => $order->source_name,
                'order_category' => $orderCategory,

                /*
                 * Status remains common for both workflows:
                 *
                 * Shopify:
                 * confirmed → fulfilled/cancelled
                 *
                 * Home Food:
                 * pending → confirmed → fulfilled/cancelled
                 */
                'status' => $order->status,
                'created_at' => optional($order->created_at)
                    ? $order->created_at->toIso8601String()
                    : null,

                'subtotal' => (float) ($order->subtotal ?? 0),
                'discount' => (float) ($order->discount ?? 0),
                'shipping_price' => (float) ($order->shipping_price ?? 0),
                'tax' => (float) ($order->tax ?? 0),
                'total' => (float) ($order->total ?? 0),

                'currency' => $order->currency ?: 'INR',
                'item_count' => (int) (
                    $order->item_count
                    ?? $order->items->sum('quantity')
                ),

                'confirmed' => (bool) $order->confirmed,
                'cancelled' => (bool) $order->cancelled,
                'closed' => (bool) $order->closed,

                'meta' => $meta,
            ];
        })->values();

        /*
         * Flutter currently expects a plain JSON array.
         */
        return response()->json($output);
    }
}
