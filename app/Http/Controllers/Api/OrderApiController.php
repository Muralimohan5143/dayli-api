<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrderApiController extends Controller
{
    // GET /api/orders/{id}
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();

            if (! $user) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            /*
             * Important:
             * Only allow the logged-in customer to open their own order.
             *
             * The id may be:
             * - orders.id
             * - Shopify order id
             */
            $order = Order::query()
                ->with('items')
                ->where('customer_id', (int) $user->id)
                ->where(function ($query) use ($id) {
                    $query->where('id', $id)
                        ->orWhere('shopify_id', $id);
                })
                ->firstOrFail();

            /*
             * Order model casts meta to array.
             */
            $meta = is_array($order->meta)
                ? $order->meta
                : [];

            /*
             * Decode Shopify metafields safely.
             */
            $metafields = [];

            if (! empty($order->metafields)) {
                if (is_array($order->metafields)) {
                    $metafields = $order->metafields;
                } elseif (is_string($order->metafields)) {
                    $decoded = json_decode(
                        $order->metafields,
                        true
                    );

                    if (is_array($decoded)) {
                        $metafields = $decoded;
                    }
                }
            }

            $meta['nagar'] =
                $metafields['nagar']
                ?? $meta['nagar']
                ?? 'My Area';

            $meta['address'] =
                $metafields['address']
                ?? $meta['address']
                ?? '';

            /*
             * These fields are already cast to arrays in Order model.
             */
            $shippingAddress = is_array($order->shipping_address)
                ? $order->shipping_address
                : null;

            $shippingMethods = is_array($order->shipping_methods)
                ? $order->shipping_methods
                : [];

            /*
             * If Shopify address metafields are empty,
             * build an address from Shopify shipping_address.
             */
            if (
                empty($meta['address'])
                && is_array($shippingAddress)
            ) {
                $addressParts = array_filter([
                    $shippingAddress['address1'] ?? null,
                    $shippingAddress['address2'] ?? null,
                    $shippingAddress['city'] ?? null,
                    $shippingAddress['province'] ?? null,
                    $shippingAddress['zip'] ?? null,
                ]);

                $meta['address'] = implode(', ', $addressParts);
            }

            $createdAtIso = $this->dateToIso(
                $order->created_at
            );

            $cancelledAtIso = $this->dateToIso(
                $order->cancelled_at
            );

            $orderCategory =
                $order->order_type === 'shopify'
                ? 'shop'
                : (
                    $order->source_name === 'dayli_home_food'
                    ? 'home_food'
                    : 'other'
                );

            $displayNumber =
                $order->shopify_name
                ?: $order->number
                ?: (
                    $orderCategory === 'home_food'
                    ? 'HF-' . $order->id
                    : 'ORD-' . $order->id
                );

            $financialStatusLabel =
                $order->financial_status_label
                ?: $order->display_financial_status
                ?: $order->financial_status
                ?: 'Pending';

            $fulfillmentStatusLabel =
                $order->fulfillment_status_label
                ?: $order->display_fulfillment_status
                ?: $order->fulfillment_status
                ?: (
                    $order->status === 'fulfilled'
                    ? 'Fulfilled'
                    : 'Unfulfilled'
                );

            $data = [
                'id' => (int) $order->id,
                'number' => $displayNumber,

                'order_type' => $order->order_type,
                'source_name' => $order->source_name,
                'order_category' => $orderCategory,

                'status' => $order->status,
                'created_at' => $createdAtIso,

                'subtotal' => (float) ($order->subtotal ?? 0),
                'tax' => (float) ($order->tax ?? 0),
                'discount' => (float) ($order->discount ?? 0),
                'shipping_price' => (float) (
                    $order->shipping_price ?? 0
                ),
                'total' => (float) ($order->total ?? 0),
                'currency' => $order->currency ?: 'INR',

                'item_count' => (int) (
                    $order->item_count
                    ?? $order->items->sum('quantity')
                ),

                'financial_status' => $order->financial_status,
                'financial_status_label' => $financialStatusLabel,

                'fulfillment_status' => $order->fulfillment_status,
                'fulfillment_status_label' => $fulfillmentStatusLabel,

                'shipping_methods' => $shippingMethods,
                'shipping_address' => $shippingAddress,

                'nagar' => $meta['nagar'],
                'address' => $meta['address'],

                /*
                 * Home Food information.
                 */
                'chef_id' => $order->vendor_id
                    ? (int) $order->vendor_id
                    : null,

                'meal_type' => $meta['meal_type'] ?? null,
                'food_menu_today_id' =>
                $meta['food_menu_today_id'] ?? null,

                'confirmed' => (bool) $order->confirmed,
                'cancelled' => (bool) $order->cancelled,
                'closed' => (bool) $order->closed,

                'cancelled_at' => $cancelledAtIso,
                'cancel_reason' => $order->cancel_reason,
                'cancel_reason_label' =>
                $order->cancel_reason_label,

                'meta' => $meta,

                'items' => $order->items
                    ->map(function ($item) {
                        return [
                            'id' => (int) $item->id,
                            'title' => $item->title,
                            'variant' => $item->variant,
                            'quantity' => (int) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'line_total' => (float) $item->line_total,
                            'image_url' => $item->image_url,
                        ];
                    })
                    ->values(),
            ];

            return response()->json([
                'order' => $data,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'message' => 'Order not found.',
            ], 404);
        } catch (\Throwable $e) {
            Log::error('OrderApiController@show failed', [
                'id' => $id,
                'user_id' => optional($request->user())->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Order details failed.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function dateToIso($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }
}
