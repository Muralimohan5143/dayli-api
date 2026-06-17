<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShopifyCart;
use App\Models\ShopifyCartLine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\ShopifyCartService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopifyCartController extends Controller
{
    private function userId(Request $request): int
    {
        return (int) ($request->user()?->id ?? $request->input('user_id') ?? 11343);
    }

    private function activeCart(int $userId): ShopifyCart
    {
        return ShopifyCart::firstOrCreate(
            [
                'user_id' => $userId,
                'status' => 'active',
            ],
            [
                'customer_id' => $userId,
                'shopify_cart_gid' => 'LOCAL-' . $userId,
                'currency_code' => 'INR',
                'subtotal' => 0,
                'total' => 0,
                'total_tax' => 0,
                'status' => 'active',
            ]
        );
    }

    private function recalc(ShopifyCart $cart): void
    {
        $subtotal = ShopifyCartLine::where('shopify_cart_id', $cart->id)->sum('line_total');

        $cart->update([
            'subtotal' => $subtotal,
            'total' => $subtotal,
            'total_tax' => 0,
        ]);
    }

    public function add(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'product_id' => ['nullable'],
            'variant_id' => ['required'],
            'title' => ['required', 'string'],
            'variant_title' => ['nullable', 'string'],
            'qty' => ['nullable', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric'],
        ]);

        $userId = $this->userId($request);
        $cart = $this->activeCart($userId);

        $qty = (int) ($data['qty'] ?? 1);
        $unitPrice = (float) $data['unit_price'];

        $line = ShopifyCartLine::where('shopify_cart_id', $cart->id)
            ->where('variant_id', $data['variant_id'])
            ->first();

        if ($line) {
            $line->qty += $qty;
            $line->line_total = $line->qty * (float) $line->unit_price;
            $line->save();
        } else {
            ShopifyCartLine::create([
                'shopify_cart_id' => $cart->id,
                'shopify_line_gid' => null,
                'product_id' => $data['product_id'] ?? null,
                'variant_id' => $data['variant_id'],
                'shopify_product_gid' => isset($data['product_id'])
                    ? 'gid://shopify/Product/' . $data['product_id']
                    : null,
                'shopify_variant_gid' => 'gid://shopify/ProductVariant/' . $data['variant_id'],
                'title' => $data['title'],
                'variant_title' => $data['variant_title'] ?? null,
                'qty' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $qty * $unitPrice,
                'raw_shopify_json' => $data,
            ]);
        }

        $this->recalc($cart);

        return $this->show($request);
    }

    public function show(Request $request)
    {
        $userId = $this->userId($request);

        $cart = ShopifyCart::where('user_id', $userId)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if (!$cart) {
            return response()->json([
                'success' => true,
                'cart' => null,
                'items' => [],
                'total_qty' => 0,
                'total' => 0,
            ]);
        }

        $items = ShopifyCartLine::where('shopify_cart_id', $cart->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'cart' => $cart,
            'items' => $items,
            'total_qty' => (int) $items->sum('qty'),
            'total' => (float) $items->sum('line_total'),
        ]);
    }

    public function updateQty(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'line_id' => ['required', 'integer'],
            'qty' => ['required', 'integer', 'min:0'],
        ]);

        $userId = $this->userId($request);
        $cart = $this->activeCart($userId);

        $line = ShopifyCartLine::where('shopify_cart_id', $cart->id)
            ->where('id', $data['line_id'])
            ->firstOrFail();

        if ((int) $data['qty'] === 0) {
            $line->delete();
        } else {
            $line->qty = (int) $data['qty'];
            $line->line_total = $line->qty * (float) $line->unit_price;
            $line->save();
        }

        $this->recalc($cart);

        return $this->show($request);
    }

    public function remove(Request $request)
    {
        $data = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'line_id' => ['required', 'integer'],
        ]);

        $userId = $this->userId($request);
        $cart = $this->activeCart($userId);

        ShopifyCartLine::where('shopify_cart_id', $cart->id)
            ->where('id', $data['line_id'])
            ->delete();

        $this->recalc($cart);

        return $this->show($request);
    }

    public function checkout(Request $request, ShopifyCartService $service)
    {
        $userId = $this->userId($request);

        $cart = ShopifyCart::where('user_id', $userId)
            ->where('status', 'active')
            ->latest('id')
            ->firstOrFail();

        $lines = ShopifyCartLine::where('shopify_cart_id', $cart->id)->get();

        if ($lines->isEmpty()) {
            return response()->json(['message' => 'Cart is empty'], 422);
        }

        $shopifyLines = $lines->map(fn($line) => [
            'product_id' => $line->product_id,
            'variant_id' => $line->variant_id,
            'title' => $line->title,
            'variant_title' => $line->variant_title,
            'qty' => $line->qty,
            'unit_price' => $line->unit_price,
        ])->values()->all();

        $shopifyCart = $service->createCart($shopifyLines);

        $cart->update([
            'shopify_cart_gid' => $shopifyCart['id'],
            'checkout_url' => $shopifyCart['checkoutUrl'] ?? null,
            'status' => 'checkout_started',
            'raw_shopify_json' => $shopifyCart,
        ]);

        return response()->json([
            'success' => true,
            'local_cart_id' => $cart->id,
            'cart_id' => $shopifyCart['id'] ?? null,
            'checkout_url' => $shopifyCart['checkoutUrl'] ?? null,
        ]);
    }
    public function latestShopifyOrder(Request $request)
    {
        $userId = $this->userId($request);

        $order = Order::where('customer_id', $userId)
            ->where('order_type', 'shopify')
            ->latest('id')
            ->first();

        if (!$order) {
            return response()->json([
                'success' => true,
                'order' => null,
                'items' => [],
            ]);
        }

        $items = OrderItem::where('order_id', $order->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'order' => $order,
            'items' => $items,
        ]);
    }
}
