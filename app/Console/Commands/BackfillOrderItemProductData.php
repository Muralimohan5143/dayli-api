<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class BackfillOrderItemProductData extends Command
{
    protected $signature   = 'dayli:backfill-order-items-products';
    protected $description = 'Backfill product_id, variant_id, product_url, image_url from order_items.meta using Shopify products API';

    protected string $baseUrl;
    protected ?string $token = null;
    protected string $apiVersion;

    // 🧠 Simple in-memory cache: productId => product array|null
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

        $this->info('Backfilling order_items product fields from meta...');

        // Process in chunks to avoid memory issues
        $count = 0;

        DB::table('order_items')
            ->whereNotNull('meta')
            ->where(function ($q) {
                $q->whereNull('product_id')
                    ->orWhereNull('product_url')
                    ->orWhereNull('image_url');
            })
            ->orderBy('id')
            ->chunkById(100, function ($items) use (&$count) {
                foreach ($items as $item) {
                    $this->backfillOneItem($item);
                    $count++;
                }

                $this->info("Processed {$count} items so far...");
            });

        $this->info("Done. Total order_items processed: {$count}");

        return self::SUCCESS;
    }

    /**
     * Backfill a single order_items row
     */
    protected function backfillOneItem($item): void
    {
        $meta = json_decode($item->meta ?? '', true);

        if (!is_array($meta)) {
            $this->warn("Skipping item {$item->id} – invalid meta JSON");
            return;
        }

        // from sample meta: "product_id": 8386934309138, "variant_id": 46897026433298, ...
        $productId = isset($meta['product_id']) ? (int) $meta['product_id'] : null;
        $variantId = isset($meta['variant_id']) ? (int) $meta['variant_id'] : null;

        if (!$productId) {
            $this->warn("Skipping item {$item->id} – no product_id in meta");
            return;
        }

        $productUrl = $item->product_url;
        $imageUrl   = $item->image_url;

        $product = $this->getShopifyProduct($productId);

        if ($product) {
            $handle     = $product['handle'] ?? null;
            $productUrl = $productUrl ?: $this->buildProductUrl($handle);

            // Prefer main image, else first images[]
            if (!$imageUrl) {
                if (!empty($product['image']['src'])) {
                    $imageUrl = $product['image']['src'];
                } elseif (!empty($product['images'][0]['src'])) {
                    $imageUrl = $product['images'][0]['src'];
                }
            }
        } else {
            $this->warn("Item {$item->id}: failed to fetch product {$productId} from Shopify");
        }

        DB::table('order_items')
            ->where('id', $item->id)
            ->update([
                'product_id'  => $productId,
                'variant_id'  => $variantId,
                'product_url' => $productUrl,
                'image_url'   => $imageUrl,
                'updated_at'  => now(),
            ]);
    }

    /**
     * Fetch Shopify product and cache result.
     */
    protected function getShopifyProduct(int $productId): ?array
    {
        if (isset($this->productCache[$productId])) {
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
     * Uses SHOPIFY_DAYLI_STOREFRONT_URL if set, else falls back to .myshopify.com
     */
    protected function buildProductUrl(?string $handle): ?string
    {
        if (!$handle) {
            return null;
        }

        $storefront = config('services.shopify_dayli.storefront_url')
            ?: ('https://' . config('services.shopify_dayli.store_domain'));

        return rtrim($storefront, '/') . '/products/' . $handle;
    }
}
