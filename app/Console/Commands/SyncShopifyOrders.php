<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SyncShopifyOrders extends Command
{
    protected $signature   = 'dayli:sync-shopify-orders';
    protected $description = 'Sync orders from Shopify into dayli.orders and order_items tables';

    protected string $baseUrl;
    protected ?string $token = null;
    protected string $apiVersion;

    // 🔹 Cache to avoid calling Shopify for same product again and again
    protected array $productCache = [];

    public function __construct()
    {
        parent::__construct();

        $storeDomain      = config('services.shopify_dayli.store_domain');
        $this->baseUrl    = 'https://' . $storeDomain;
        $this->token = config('services.shopify_dayli.access_token')
            ?: env('SHOPIFY_DAYLI_ACCESS_TOKEN');
        $this->apiVersion = config('services.shopify_dayli.api_version');
    }

    public function handle(): int
    {
        if (!$this->token || !$this->baseUrl) {
            $this->error('Shopify config missing. Check services.php and .env (SHOPIFY_DAYLI_*)');
            return self::FAILURE;
        }

        $this->info('Syncing Shopify orders → dayli.orders');

        $pageInfo    = null;
        $totalSynced = 0;

        do {
            $response = $this->fetchOrdersPage($pageInfo);

            if ($response->failed()) {
                $this->error('Shopify API error: ' . $response->body());
                return self::FAILURE;
            }

            $data   = $response->json();
            $orders = $data['orders'] ?? [];

            if (empty($orders)) {
                break;
            }

            DB::beginTransaction();

            try {
                foreach ($orders as $shopifyOrder) {
                    $this->syncOneOrder($shopifyOrder);
                    $totalSynced++;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error('Error syncing batch: ' . $e->getMessage());
                return self::FAILURE;
            }

            $pageInfo = $this->extractNextPageInfo($response);
            $this->info("Synced batch of " . count($orders) . ", total so far: {$totalSynced}");
        } while ($pageInfo);

        $this->info("Done. Total orders synced: {$totalSynced}");

        return self::SUCCESS;
    }

    /**
     * Fetch one page of Shopify orders (cursor-based pagination).
     */
    protected function fetchOrdersPage(?string $pageInfo)
    {
        $query = [
            'limit'  => 250,
            'fields' => implode(',', [
                // identity
                'id',
                'name',
                'order_number',
                'confirmation_number',
                'order_status_url',

                // dates + cancel
                'created_at',
                'cancelled_at',
                'cancel_reason',

                // money
                'subtotal_price',
                'total_tax',
                'total_discounts',
                'total_price',
                'currency',

                // status
                'financial_status',
                'fulfillment_status',

                // customer
                'email',
                'customer',

                // shipping + tags
                'shipping_address',
                'shipping_lines',
                'tags',

                // items
                'line_items',
            ]),
        ];

        if ($pageInfo) {
            $query['page_info'] = $pageInfo;
        } else {
            $query['status'] = 'any';
            $query['order']  = 'created_at asc';
        }

        return Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
        ])->get("{$this->baseUrl}/admin/api/{$this->apiVersion}/orders.json", $query);
    }

    /**
     * Parse Link header to get next page_info (cursor).
     */
    protected function extractNextPageInfo($response): ?string
    {
        $link = $response->header('Link');

        if (!$link) {
            return null;
        }

        foreach (explode(',', $link) as $part) {
            if (Str::contains($part, 'rel="next"')) {
                if (preg_match('/page_info=([^&>]+)/', $part, $m)) {
                    return $m[1];
                }
            }
        }

        return null;
    }

    /**
     * Sync a single Shopify order into `orders` + `order_items`.
     *
     * NOTE: Keeping your existing behavior:
     * `orders.customer_id` = Shopify customer.id
     */
    protected function syncOneOrder(array $shopifyOrder): void
    {

        logger()->info('ORDER ship', [
            'order' => $shopifyOrder['name'] ?? null,
            'shipping_address' => $shopifyOrder['shipping_address'] ?? null,
            'customer_default_address' => $shopifyOrder['customer']['default_address'] ?? null,
        ]);
        $number = $shopifyOrder['name'] ?? null; // e.g. "#1001"
        $shopifyOrderId = isset($shopifyOrder['id']) ? (int) $shopifyOrder['id'] : null;
        $orderMetafields = $this->fetchOrderMetafields($shopifyOrderId);

        if (!$number) {
            $this->warn('Skipped order without name: ' . json_encode($shopifyOrder['id'] ?? null));
            return;
        }

        if (!$shopifyOrderId) {
            $this->warn("Skipped order {$number} – missing Shopify id");
            return;
        }

        // 1️⃣ Get Shopify customer ID (we store this directly in orders.customer_id)
        $shopifyCustomerId = $this->mapShopifyCustomerId($shopifyOrder['customer'] ?? null);

        if (!$shopifyCustomerId) {
            $this->warn("Skipping order {$number} – no Shopify customer id");
            return;
        }

        // 2️⃣ Map Shopify → our status enum
        $status = $this->mapStatus(
            $shopifyOrder['financial_status'] ?? null,
            $shopifyOrder['fulfillment_status'] ?? null,
            $shopifyOrder['cancelled_at'] ?? null,
        );

        // 3️⃣ Build meta (keep your existing raw meta approach)
        $meta = [
            'shopify_id'          => $shopifyOrderId,
            'shopify_customer_id' => $shopifyCustomerId,
            'currency'            => $shopifyOrder['currency'] ?? null,
            'financial_status'    => $shopifyOrder['financial_status'] ?? null,
            'fulfillment_status'  => $shopifyOrder['fulfillment_status'] ?? null,
            'raw'                 => $shopifyOrder,
        ];

        // 4️⃣ Prepare new Shopify fields (new columns you added via migration)
        $financialLabel   = $this->labelFinancialStatus($shopifyOrder['financial_status'] ?? null);
        $fulfillmentLabel = $this->labelFulfillmentStatus($shopifyOrder['fulfillment_status'] ?? null);

        $cancelledAt = $shopifyOrder['cancelled_at'] ?? null;
        $isCancelled = !empty($cancelledAt);

        // tags in REST may come as comma string "tag1, tag2"
        $tagsJson = $this->normalizeTagsToJson($shopifyOrder['tags'] ?? null);

        // shipping methods from shipping_lines
        $shippingLines = $shopifyOrder['shipping_lines'] ?? [];
        $shippingPrice = $this->extractShippingPrice($shippingLines);
        $shippingAddress = $shopifyOrder['shipping_address'] ?? null;

        $addr1 = is_array($shippingAddress) ? ($shippingAddress['address1'] ?? null) : null;

        if (empty($addr1)) {
            $fallback = $shopifyOrder['customer']['default_address'] ?? null;
            if (is_array($fallback)) {
                $shippingAddress = $fallback;
            }
        }

        // line_items subtotal (use subtotal_price if present)
        $lineItemsSubtotal = $shopifyOrder['subtotal_price'] ?? null;

        // item_count (Shopify order doesn't always give direct field; we compute)
        $itemCount = $this->computeItemCount($shopifyOrder['line_items'] ?? []);

        // discounts json: keep full object (Shopify has many discount fields; using total_discounts + raw lines if needed)
        $discountsJson = $this->normalizeDiscountsJson($shopifyOrder);

        // metafields: not included in orders.json by default; keep empty object
        $metafieldsJson = json_encode(new \stdClass());

        // 5️⃣ Upsert: find by shopify_id OR (fallback) by number
        $existingOrder = DB::table('orders')->where('shopify_id', $shopifyOrderId)->first();

        if (!$existingOrder) {
            // fallback to number because old data may exist without shopify_id
            $existingOrder = DB::table('orders')->where('number', $number)->first();
        }

        $commonPayload = [
            // keep your current design
            'customer_id'    => $shopifyCustomerId, // 👈 Shopify customer ID (kept)
            'vendor_id'      => null,
            'zone_id'        => null,
            'draft_order_id' => null,
            'number'         => $number,
            'status'         => $status,

            // your existing totals
            'subtotal'       => $shopifyOrder['subtotal_price'] ?? 0,
            'tax'            => $shopifyOrder['total_tax'] ?? 0,
            'discount'       => $shopifyOrder['total_discounts'] ?? 0,
            'total'          => $shopifyOrder['total_price'] ?? 0,

            // ✅ new fields (from your requirements)
            'shopify_id'              => $shopifyOrderId,
            'order_number'            => isset($shopifyOrder['order_number']) ? (int) $shopifyOrder['order_number'] : null,
            'shopify_name'            => $shopifyOrder['name'] ?? null,
            'confirmation_number'     => $shopifyOrder['confirmation_number'] ?? null,

            'email'                   => $shopifyOrder['email'] ?? ($shopifyOrder['customer']['email'] ?? null),

            'financial_status'        => $shopifyOrder['financial_status'] ?? null,
            'financial_status_label'  => $financialLabel,

            'fulfillment_status'      => $shopifyOrder['fulfillment_status'] ?? null,
            'fulfillment_status_label' => $fulfillmentLabel,

            'cancelled'               => $isCancelled,
            'cancelled_at'            => $cancelledAt,
            'cancel_reason'           => $shopifyOrder['cancel_reason'] ?? null,
            'cancel_reason_label'     => $this->labelCancelReason($shopifyOrder['cancel_reason'] ?? null),

            'order_status_url'        => $shopifyOrder['order_status_url'] ?? null,

            'item_count'              => $itemCount,
            'line_items_subtotal_price' => $lineItemsSubtotal,
            'shipping_price'          => $shippingPrice,

            // not present in orders.json normally; leave null unless you later enrich from transactions/refunds
            'total_refunded_amount'   => null,
            'total_net_amount'        => $shopifyOrder['total_price'] ?? null,
            'currency'                => $shopifyOrder['currency'] ?? null,

            'tags'                    => $tagsJson,
            'shipping_address' => !empty($shippingAddress)
                ? json_encode($shippingAddress)
                : null,

            'shipping_methods'        => !empty($shippingLines)
                ? json_encode($shippingLines)
                : null,
            'discounts'               => $discountsJson,
            'metafields' => !empty($orderMetafields) ? json_encode($orderMetafields) : null,

            'meta'                    => json_encode($meta),

            // keep timestamps behavior
            'updated_at'              => now(),
        ];

        if ($existingOrder) {
            $orderId = $existingOrder->id;

            DB::table('orders')
                ->where('id', $orderId)
                ->update(array_merge($commonPayload, [
                    'created_at' => $shopifyOrder['created_at'] ?? $existingOrder->created_at,
                ]));
        } else {
            $orderId = DB::table('orders')->insertGetId(array_merge($commonPayload, [
                'created_at' => $shopifyOrder['created_at'] ?? now(),
            ]));
        }

        // 6️⃣ Refresh line items: delete & recreate (kept)
        DB::table('order_items')->where('order_id', $orderId)->delete();

        foreach ($shopifyOrder['line_items'] ?? [] as $line) {
            $qty   = $line['quantity'] ?? 1;
            $price = $line['price'] ?? 0;

            $productId = isset($line['product_id']) ? (int) $line['product_id'] : null;
            $variantId = isset($line['variant_id']) ? (int) $line['variant_id'] : null;

            $productUrl = null;
            $imageUrl   = null;

            if ($productId) {
                $product = $this->getShopifyProduct($productId);

                if ($product) {
                    // Handle → URL
                    $handle     = $product['handle'] ?? null;
                    $productUrl = $this->buildProductUrl($handle);

                    // Prefer main image, then first images[]
                    if (!empty($product['image']['src'])) {
                        $imageUrl = $product['image']['src'];
                    } elseif (!empty($product['images'][0]['src'])) {
                        $imageUrl = $product['images'][0]['src'];
                    }
                }
            }

            DB::table('order_items')->insert([
                'order_id'    => $orderId,
                'product_id'  => $productId,
                'variant_id'  => $variantId,
                'sku'         => $line['sku'] ?? null,
                'title'       => $line['title'] ?? '',
                'variant'     => $line['variant_title'] ?? null,
                'brand'       => $line['vendor'] ?? null,
                'quantity'    => $qty,
                'unit_price'  => $price,
                'line_total'  => $qty * $price,
                'product_url' => $productUrl,
                'image_url'   => $imageUrl,
                'meta'        => json_encode($line),
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
        }

        $this->line("Synced order {$number} (Shopify: {$shopifyOrderId}, Local: {$orderId}, status: {$status})");
    }

    /**
     * Directly return Shopify customer.id
     *
     * This will be stored in orders.customer_id
     */
    protected function mapShopifyCustomerId(?array $shopifyCustomer): ?int
    {
        if (!$shopifyCustomer) {
            return null;
        }

        $shopifyCustomerId = $shopifyCustomer['id'] ?? null;

        if (!$shopifyCustomerId) {
            return null;
        }

        return (int) $shopifyCustomerId;
    }

    /**
     * Map Shopify financial + fulfillment status → our `orders.status`
     * enum('draft','pending','confirmed','fulfilled','cancelled')
     */
    protected function mapStatus(?string $financial, ?string $fulfillment, ?string $cancelledAt): string
    {
        if ($cancelledAt) {
            return 'cancelled';
        }

        $financial   = $financial ?: 'pending';
        $fulfillment = $fulfillment ?: 'unfulfilled';

        if (
            in_array($financial, ['paid', 'partially_paid']) &&
            in_array($fulfillment, ['fulfilled'])
        ) {
            return 'fulfilled';
        }

        if (
            in_array($financial, ['paid', 'partially_paid']) &&
            in_array($fulfillment, ['unfulfilled', 'partial'])
        ) {
            return 'confirmed'; // paid but not fully fulfilled
        }

        if (in_array($financial, ['pending', 'authorized'])) {
            return 'pending';
        }

        return 'draft';
    }

    /**
     * Fetch Shopify product once and cache.
     */
    protected function getShopifyProduct(int $productId): ?array
    {
        if (array_key_exists($productId, $this->productCache)) {
            return $this->productCache[$productId];
        }

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
        ])->get("{$this->baseUrl}/admin/api/{$this->apiVersion}/products/{$productId}.json");

        if ($response->failed()) {
            $this->warn("Failed to fetch product {$productId}: " . $response->body());
            $this->productCache[$productId] = null;
            return null;
        }

        $json    = $response->json();
        $product = $json['product'] ?? null;

        $this->productCache[$productId] = $product;

        return $product;
    }

    /**
     * Build storefront product URL from product handle.
     */
    protected function buildProductUrl(?string $handle): ?string
    {
        if (!$handle) {
            return null;
        }

        $storefrontDomain = config('services.shopify_dayli.store_domain'); // e.g. dayli-in.myshopify.com

        return 'https://' . rtrim($storefrontDomain, '/') . '/products/' . $handle;
    }

    // --------------------------
    // Helpers for new fields
    // --------------------------

    protected function computeItemCount(array $lineItems): int
    {
        $c = 0;
        foreach ($lineItems as $li) {
            $c += (int) ($li['quantity'] ?? 0);
        }
        return $c;
    }

    protected function normalizeTagsToJson($tags): ?string
    {
        if ($tags === null) {
            return null;
        }

        // Shopify REST usually returns tags as comma-separated string
        if (is_string($tags)) {
            $arr = array_values(array_filter(array_map('trim', explode(',', $tags))));
            return json_encode($arr);
        }

        // if already array
        if (is_array($tags)) {
            return json_encode(array_values($tags));
        }

        return null;
    }

    protected function extractShippingPrice(array $shippingLines): ?string
    {
        if (empty($shippingLines)) {
            return null;
        }

        // Shopify shipping_lines[] has "price" usually as string
        // If multiple shipping lines, sum them
        $sum = 0.0;
        foreach ($shippingLines as $sl) {
            $sum += (float) ($sl['price'] ?? 0);
        }

        return number_format($sum, 2, '.', '');
    }

    protected function normalizeDiscountsJson(array $shopifyOrder): ?string
    {
        // Keep it simple but useful:
        // store total_discounts + discount_codes + discount_applications if present
        $payload = [
            'total_discounts'      => $shopifyOrder['total_discounts'] ?? null,
            'discount_codes'       => $shopifyOrder['discount_codes'] ?? null,
            'discount_applications' => $shopifyOrder['discount_applications'] ?? null,
        ];

        // If everything null/empty, return null to keep DB clean
        $hasAny = false;
        foreach ($payload as $v) {
            if (!empty($v)) {
                $hasAny = true;
                break;
            }
        }

        return $hasAny ? json_encode($payload) : null;
    }

    protected function labelFinancialStatus(?string $status): ?string
    {
        if (!$status) return null;

        $map = [
            'paid'            => 'Paid',
            'pending'         => 'Pending',
            'authorized'      => 'Authorized',
            'partially_paid'  => 'Partially paid',
            'refunded'        => 'Refunded',
            'partially_refunded' => 'Partially refunded',
            'voided'          => 'Voided',
        ];

        return $map[$status] ?? Str::headline($status);
    }

    protected function labelFulfillmentStatus(?string $status): ?string
    {
        if (!$status) return null;

        $map = [
            'fulfilled'   => 'Fulfilled',
            'unfulfilled' => 'Unfulfilled',
            'partial'     => 'Partial',
        ];

        return $map[$status] ?? Str::headline($status);
    }

    protected function labelCancelReason(?string $reason): ?string
    {
        if (!$reason) return null;

        $map = [
            'customer' => 'Customer',
            'inventory' => 'Inventory',
            'fraud'    => 'Fraud',
            'declined' => 'Payment declined',
            'other'    => 'Other',
        ];

        return $map[$reason] ?? Str::headline($reason);
    }
    protected function fetchOrderMetafields(int $shopifyOrderId): array
    {
        $gid = "gid://shopify/Order/{$shopifyOrderId}";

        $query = <<<'GQL'
query($id: ID!) {
  order(id: $id) {
    nagar: metafield(namespace: "custom", key: "nagar") { value }
    address: metafield(namespace: "custom", key: "address") { value }
  }
}
GQL;

        $resp = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type'           => 'application/json',
        ])->post("{$this->baseUrl}/admin/api/{$this->apiVersion}/graphql.json", [
            'query'     => $query,
            'variables' => ['id' => $gid],
        ]);

        if ($resp->failed()) {
            $this->warn("Metafields fetch failed for order {$shopifyOrderId}: " . $resp->body());
            return [];
        }

        $json = $resp->json();
        $order = $json['data']['order'] ?? null;
        if (!$order) return [];

        return [
            'nagar'   => $order['nagar']['value']   ?? null,
            'address' => $order['address']['value'] ?? null,
        ];
    }
}
