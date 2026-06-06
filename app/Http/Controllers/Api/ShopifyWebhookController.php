<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopifyWebhookController extends Controller
{
    public function ordersCreate(Request $request)
    {
        $payload = $request->all();

        $shopifyOrderId = $payload['id'] ?? null;

        if (!$shopifyOrderId) {
            return response()->json(['success' => false, 'message' => 'Missing Shopify order id'], 422);
        }

        DB::transaction(function () use ($payload, $shopifyOrderId) {
            $customer = $this->findOrCreateCustomer($payload);

            $subtotal = (float)($payload['subtotal_price'] ?? 0);
            $tax = (float)($payload['total_tax'] ?? 0);
            $discount = (float)($payload['total_discounts'] ?? 0);
            $total = (float)($payload['total_price'] ?? 0);

            $order = Order::updateOrCreate(
                ['shopify_id' => $shopifyOrderId],
                [
                    'order_type' => 'shopify',
                    'shopify_order_gid' => 'gid://shopify/Order/' . $shopifyOrderId,
                    'shopify_legacy_id' => $shopifyOrderId,
                    'order_number' => $payload['order_number'] ?? null,
                    'shopify_name' => $payload['name'] ?? null,
                    'name' => $payload['name'] ?? null,
                    'confirmation_number' => $payload['confirmation_number'] ?? null,

                    'customer_id' => $customer->id,
                    'delivery_date' => now()->toDateString(),
                    'delivery_status' => 'pending',

                    'number' => $payload['name'] ?? ('SHOP-' . $shopifyOrderId),
                    'status' => 'confirmed',
                    'confirmed' => true,
                    'closed' => !empty($payload['closed_at']),
                    'requires_shipping' => (bool)($payload['requires_shipping'] ?? true),
                    'taxes_included' => (bool)($payload['taxes_included'] ?? false),
                    'tax_exempt' => (bool)($payload['tax_exempt'] ?? false),
                    'test' => (bool)($payload['test'] ?? false),
                    'unpaid' => (($payload['financial_status'] ?? null) !== 'paid'),

                    'financial_status' => $payload['financial_status'] ?? null,
                    'display_financial_status' => $payload['financial_status'] ?? null,
                    'fulfillment_status' => $payload['fulfillment_status'] ?? null,
                    'display_fulfillment_status' => $payload['fulfillment_status'] ?? null,

                    'email' => $payload['email'] ?? null,
                    'phone' => $payload['phone'] ?? ($payload['shipping_address']['phone'] ?? null),
                    'order_status_url' => $payload['order_status_url'] ?? null,
                    'status_page_url' => $payload['order_status_url'] ?? null,

                    'item_count' => count($payload['line_items'] ?? []),
                    'currency' => $payload['currency'] ?? 'INR',
                    'currency_code' => $payload['currency'] ?? 'INR',

                    'subtotal' => $subtotal,
                    'tax' => $tax,
                    'discount' => $discount,
                    'total' => $total,

                    'current_subtotal' => $subtotal,
                    'current_tax' => $tax,
                    'current_discounts' => $discount,
                    'current_shipping' => (float)($payload['total_shipping_price_set']['shop_money']['amount'] ?? 0),
                    'current_total' => $total,

                    'shipping_address' => $payload['shipping_address'] ?? null,
                    'shipping_address_json' => $payload['shipping_address'] ?? null,
                    'billing_address_json' => $payload['billing_address'] ?? null,
                    'shipping_methods' => $payload['shipping_lines'] ?? [],
                    'discounts' => $payload['discount_codes'] ?? [],
                    'tags' => isset($payload['tags']) ? explode(',', $payload['tags']) : [],
                    'meta' => $payload,

                    'source_name' => $payload['source_name'] ?? 'shopify',
                    'note' => $payload['note'] ?? null,

                    'created_at_shopify' => $payload['created_at'] ?? null,
                    'processed_at_shopify' => $payload['processed_at'] ?? null,
                    'updated_at_shopify' => $payload['updated_at'] ?? null,
                    'cancelled_at_shopify' => $payload['cancelled_at'] ?? null,
                    'closed_at_shopify' => $payload['closed_at'] ?? null,
                ]
            );

            OrderItem::where('order_id', $order->id)->delete();

            foreach (($payload['line_items'] ?? []) as $item) {
                $qty = (int)($item['quantity'] ?? 1);
                $unitPrice = (float)($item['price'] ?? 0);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'] ?? null,
                    'variant_id' => $item['variant_id'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'title' => $item['title'] ?? 'Product',
                    'variant' => $item['variant_title'] ?? null,
                    'brand' => $item['vendor'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $qty * $unitPrice,
                    'actuals_date' => now()->toDateString(),
                    'meta' => $item,
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    private function findOrCreateCustomer(array $payload): User
    {
        $shopifyCustomerId = $payload['customer']['id'] ?? null;
        $email = $payload['email'] ?? ($payload['customer']['email'] ?? null);
        $phone = $payload['phone']
            ?? ($payload['customer']['phone'] ?? null)
            ?? ($payload['shipping_address']['phone'] ?? null);

        $query = User::query();

        if ($shopifyCustomerId) {
            $user = $query->where('shopify_customer_id', $shopifyCustomerId)->first();
            if ($user) return $user;
        }

        if ($phone) {
            $user = User::where('phone', $phone)->first();
            if ($user) return $user;
        }

        if ($email) {
            $user = User::where('email', $email)->first();
            if ($user) return $user;
        }

        $firstName = $payload['shipping_address']['first_name']
            ?? ($payload['customer']['first_name'] ?? null);

        $lastName = $payload['shipping_address']['last_name']
            ?? ($payload['customer']['last_name'] ?? null);

        return User::create([
            'shopify_customer_id' => $shopifyCustomerId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: 'Shopify Customer',
            'display_name' => trim(($firstName ?? '') . ' ' . ($lastName ?? '')) ?: 'Shopify Customer',
            'email' => $email,
            'phone' => $phone,
            'origin_system' => 'dayli',
            'account_status' => 'active',
            'default_address_json' => $payload['shipping_address'] ?? null,
        ]);
    }
}
