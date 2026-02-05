<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MyOrdersController extends Controller
{
    public function index(Request $request)
    {
        // 1️⃣ User from Sanctum token
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // 2️⃣ Shopify customer id is required (your current mapping)
        if (! $user->shopify_customer_id) {
            return response()->json([
                'message' => 'User missing shopify_customer_id. Sync / mapping required.',
            ], 422);
        }

        $shopifyId = (int) $user->shopify_customer_id;

        // Optional: allow filtering by status from API (Flutter currently filters locally, but this helps later)
        $status = $request->query('status'); // pending|confirmed|fulfilled|cancelled

        // 3️⃣ Fetch orders where orders.customer_id = Shopify customer id
        $q = DB::table('orders')
            ->where('customer_id', $shopifyId)
            ->orderBy('created_at', 'desc');

        if ($status) {
            $q->where('status', $status);
        }

        // Select only what UI needs + required meta sources
        $orders = $q->get([
            'id',
            'number',
            'shopify_name',
            'status',
            'created_at',

            // ✅ bill fields (for UI)
            'subtotal',
            'discount',
            'shipping_price',
            'tax',
            'total',

            'currency',
            'item_count',

            // ✅ sources
            'meta',
            'metafields',
        ]);

        // 4️⃣ Transform: decode meta JSON string into object (Flutter expects Map)
        $out = $orders->map(function ($o) {

            // 1) decode meta json
            $meta = [];
            if (!empty($o->meta) && is_string($o->meta)) {
                $decoded = json_decode($o->meta, true);
                if (is_array($decoded)) $meta = $decoded;
            }

            // 2) decode order metafields json (from Shopify)
            $mf = [];
            if (!empty($o->metafields)) {
                if (is_string($o->metafields)) {
                    $decoded = json_decode($o->metafields, true);
                    if (is_array($decoded)) $mf = $decoded;
                } elseif (is_array($o->metafields)) {
                    $mf = $o->metafields;
                }
            }

            // ✅ merge Shopify order metafields into meta (Flutter reads meta)
            $meta['nagar']   = $mf['nagar']   ?? ($meta['nagar'] ?? null);
            $meta['address'] = $mf['address'] ?? ($meta['address'] ?? null);

            // safe defaults
            $meta['nagar'] = $meta['nagar'] ?? 'My Area';
            $meta['address'] = $meta['address'] ?? '';

            return [
                'id'         => (int) $o->id,
                'number'     => $o->shopify_name ?: $o->number,
                'status'     => $o->status,
                'created_at' => $o->created_at,

                // ✅ bill fields for UI
                'subtotal'       => (float) ($o->subtotal ?? 0),
                'discount'       => (float) ($o->discount ?? 0),
                'shipping_price' => (float) ($o->shipping_price ?? 0),
                'tax'            => (float) ($o->tax ?? 0),
                'total'          => (float) ($o->total ?? 0),

                'currency'   => $o->currency,
                'item_count' => (int) ($o->item_count ?? 0),

                'meta' => $meta,
            ];
        })->values();


        // 5️⃣ Flutter expects a plain JSON array
        return response()->json($out);
    }
}
