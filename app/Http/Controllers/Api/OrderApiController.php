<?php

namespace App\Http\Controllers\Api;


use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class OrderApiController extends Controller
{
    // GET /api/orders/{id}
    public function show($id)
    {
        try {
            $order = Order::with('items')
                ->where('id', $id)                 // DB primary id
                ->orWhere('shopify_id', $id)       // Shopify order id
                ->firstOrFail();
            $order = Order::with('items')->findOrFail($id);

            // Decode meta safely (stored as JSON string in your sync)

            // ✅ Decode meta safely (raw)
            // $meta = [];
            // if (!empty($order->meta)) {
            //     $decoded = is_string($order->meta) ? json_decode($order->meta, true) : $order->meta;
            //     if (is_array($decoded)) $meta = $decoded;
            // }

            // ✅ Decode order metafields safely
            $mf = [];
            if (!empty($order->metafields)) {
                $decoded = is_string($order->metafields) ? json_decode($order->metafields, true) : $order->metafields;
                if (is_array($decoded)) $mf = $decoded;
            }


            // ✅ Merge metafields into meta (DON'T overwrite meta after this)
            $meta['nagar']   = $mf['nagar']   ?? ($meta['nagar'] ?? null);
            $meta['address'] = $mf['address'] ?? ($meta['address'] ?? null);

            // Decode shipping_address (still return it, but don't derive nagar from it)
            $shippingAddress = null;
            if (!empty($order->shipping_address)) {
                $shippingAddress = is_string($order->shipping_address)
                    ? json_decode($order->shipping_address, true)
                    : $order->shipping_address;
            }

            // Decode shipping_methods (shipping_lines)
            $shippingMethods = null;
            if (!empty($order->shipping_methods)) {
                $shippingMethods = is_string($order->shipping_methods)
                    ? json_decode($order->shipping_methods, true)
                    : $order->shipping_methods;
            }

            // ✅ Nagar + Address should come from order metafields (preferred) or meta fallback
            $nagar   = $meta['nagar'] ?? 'My Area';
            $address = $meta['address'] ?? '';
            // Build response


            $createdAtIso = null;
            if (!empty($order->created_at)) {
                if ($order->created_at instanceof \DateTimeInterface) {
                    $createdAtIso = $order->created_at->format(DATE_ATOM);
                } else {
                    try {
                        $createdAtIso = Carbon::parse($order->created_at)->toIso8601String();
                    } catch (\Throwable $e) {
                        $createdAtIso = (string) $order->created_at;
                    }
                }
            }

            $cancelledAtIso = null;
            if (!empty($order->cancelled_at)) {
                if ($order->cancelled_at instanceof \DateTimeInterface) {
                    $cancelledAtIso = $order->cancelled_at->format(DATE_ATOM);
                } else {
                    try {
                        $cancelledAtIso = Carbon::parse($order->cancelled_at)->toIso8601String();
                    } catch (\Throwable $e) {
                        $cancelledAtIso = (string) $order->cancelled_at;
                    }
                }
            }
            $data = [
                'id'         => $order->id,
                'number'     => $order->shopify_name ?: $order->number,
                'status'     => $order->status,
                'created_at' => $createdAtIso,

                'subtotal'   => $order->subtotal,
                'tax'        => $order->tax,
                'discount'   => $order->discount,
                'shipping_price' => $order->shipping_price ?? 0,
                'total'      => $order->total,
                'currency'   => $order->currency,

                'item_count'               => $order->item_count,
                'financial_status_label'   => $order->financial_status_label,
                'fulfillment_status_label' => $order->fulfillment_status_label,

                'shipping_methods' => $shippingMethods,
                'shipping_address' => $shippingAddress,

                // ✅ send delivery info directly (Flutter)
                'nagar'   => $nagar,
                'address' => $address,

                'cancelled'           => (bool) $order->cancelled,
                'cancelled_at'        => $cancelledAtIso,
                'cancel_reason_label' => $order->cancel_reason_label,

                'meta'  => $meta,


                'items' => ($order->items ?? collect())->map(function ($item) {
                    return [
                        'id'         => $item->id,
                        'title'      => $item->title,
                        'variant'    => $item->variant,
                        'quantity'   => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'line_total' => $item->line_total,
                        'image_url'  => $item->image_url,
                    ];
                })->values(),
            ];

            return response()->json(['order' => $data]);
        } catch (\Throwable $e) {
            Log::error('OrderApiController@show failed', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Order details failed',
                'id' => $id,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
