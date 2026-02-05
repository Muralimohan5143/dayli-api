<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ShopifySyncV3
{
    protected string $domain;
    protected string $version;
    protected string $token;

    public function __construct()
    {
        $this->domain  = rtrim(config('shopify.domain', env('SHOPIFY_STORE_DOMAIN')), '/');
        $this->version = config('shopify.version', env('SHOPIFY_API_VERSION', '2025-01'));
        $this->token   = config('shopify.token', env('SHOPIFY_ACCESS_TOKEN'));
    }

    /**
     * Pull products + variants. Writes to:
     *  - products(product_id, title, vendor, product_type, handle, tags, status, img_src, product_sub_type, timestamps)
     *  - variants(variant_id, product_id, title, sku, position, price, compare_at_price, taxable, inventory_policy, inventory_quantity, timestamps)
     */
    public function pullProducts(?string $updatedSince = null, int $pageSize = 100): int
    {
        $endpoint = "https://{$this->domain}/admin/api/{$this->version}/graphql.json";
        $after = null;
        $totalUpserts = 0;

        // Basic search filter
        $filters = ['status:*'];
        if ($updatedSince) {
            // ISO8601 recommended (e.g., 2025-08-01T00:00:00Z)
            $filters[] = "updated_at:>={$updatedSince}";
        }
        $search = implode(' ', $filters);

        do {
            $payload = [
                'query' => <<<'GQL'
query Products($first:Int!, $after:String, $search:String!) {
  products(first: $first, after: $after, query: $search) {
    pageInfo { hasNextPage endCursor }
    edges {
      node {
        id
        title
        vendor
        productType
        handle
        tags
        status
        updatedAt
        featuredImage { url }
        metafield(namespace: "custom", key: "subtype") { value }
        variants(first: 100) {
          nodes {
            id
            title
            sku
            position
            price
            compareAtPrice
            taxable
            inventoryPolicy
            inventoryQuantity
            updatedAt
          }
        }
      }
    }
  }
}
GQL,
                'variables' => ['first' => $pageSize, 'after' => $after, 'search' => $search],
            ];

            $resp = Http::retry(3, 800)->withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type'           => 'application/json',
            ])->post($endpoint, $payload);

            if (!$resp->successful()) {
                throw new \RuntimeException("Shopify HTTP {$resp->status()}: {$resp->body()}");
            }

            $data = data_get($resp->json(), 'data.products');
            if (!$data) {
                throw new \RuntimeException('No products returned. Check domain/token/scopes (need read_products).');
            }

            $after   = data_get($data, 'pageInfo.endCursor');
            $hasNext = (bool) data_get($data, 'pageInfo.hasNextPage');

            foreach ((array) data_get($data, 'edges', []) as $edge) {
                $p         = data_get($edge, 'node');
                $productId = $this->gidToInt(data_get($p, 'id'));

                DB::table('products')->updateOrInsert(
                    ['product_id' => $productId],
                    [
                        'title'            => (string) data_get($p, 'title'),
                        'vendor'           => data_get($p, 'vendor') ?? 'Dayli',
                        'product_type'     => data_get($p, 'productType') ?? 'daily-need',
                        'handle'           => data_get($p, 'handle') ?: 'empty-handle',
                        'tags'             => $this->flattenTags(data_get($p, 'tags')),
                        'status'           => data_get($p, 'status') ?: '""',
                        'img_src'          => data_get($p, 'featuredImage.url') ?: '""',
                        'product_sub_type' => data_get($p, 'metafield.value'), // <-- from metafield as-is
                        'updated_at'       => now(),
                        'created_at'       => DB::raw('COALESCE(created_at, NOW())'),
                    ]
                );

                foreach ((array) data_get($p, 'variants.nodes', []) as $v) {
                    $vid = $this->gidToInt(data_get($v, 'id'));
                    DB::table('variants')->updateOrInsert(
                        ['variant_id' => $vid],
                        [
                            'product_id'         => $productId,
                            'title'              => data_get($v, 'title'),
                            'sku'                => data_get($v, 'sku'),
                            'position'           => (int) data_get($v, 'position', 1),
                            'currency'           => 'INR',
                            'price'              => $this->toFloat(data_get($v, 'price')),
                            'compare_at_price'   => $this->nullableFloat(data_get($v, 'compareAtPrice')),
                            'taxable'            => (int) (data_get($v, 'taxable') ? 1 : 0),
                            'inventory_policy'   => data_get($v, 'inventoryPolicy') ?? 'deny',
                            'inventory_quantity' => (int) data_get($v, 'inventoryQuantity', 0),
                            'updated_at'         => now(),
                            'created_at'         => DB::raw('COALESCE(created_at, NOW())'),
                        ]
                    );
                    $totalUpserts++;
                }
            }
        } while ($hasNext);

        return $totalUpserts;
    }

    protected function gidToInt(?string $gid): int
    {
        if (!$gid) return 0;
        $parts = explode('/', $gid);
        return (int) end($parts);
    }

    protected function flattenTags($tags): string
    {
        // Shopify GraphQL returns array<string>; keep CSV like your schema example.
        if (is_array($tags)) return implode(',', $tags) ?: '""';
        if (is_string($tags) && strlen($tags)) return $tags;
        return '""';
    }

    protected function toFloat($v): float
    {
        return $v === null ? 0.0 : (float) $v;
    }

    protected function nullableFloat($v): ?float
    {
        return $v === null ? null : (float) $v;
    }
}
