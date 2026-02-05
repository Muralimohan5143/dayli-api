<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ShopifySync
{
    protected string $domain = '';
    protected string $version = '';
    protected string $token = '';

    public function __construct()
    {
        // Pull config/env and coerce to strings; avoid assigning null to typed props
        $this->domain  = rtrim((string) (config('shopify.domain') ?? env('SHOPIFY_STORE_DOMAIN') ?? ''), '/');
        $this->version = (string) (config('shopify.version') ?? env('SHOPIFY_API_VERSION') ?? '2025-01');
        $this->token   = (string) (config('shopify.token')   ?? env('SHOPIFY_ACCESS_TOKEN') ?? '');

        // Validate early with clear messages
        if ($this->domain === '') {
            throw new \InvalidArgumentException(
                "Shopify domain is missing. Set config('shopify.domain') or SHOPIFY_STORE_DOMAIN in .env"
            );
        }
        if ($this->token === '') {
            throw new \InvalidArgumentException(
                "Shopify access token is missing. Set config('shopify.token') or SHOPIFY_ACCESS_TOKEN in .env"
            );
        }
    }

    public function pullProducts(?string $updatedSince = null, int $pageSize = 100): int
    {
        $endpoint = "https://{$this->domain}/admin/api/{$this->version}/graphql.json";
        $after = null;
        $totalUpserts = 0;

        // Build Shopify search query
        $filters = ['status:*'];
        if ($updatedSince) {
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
      cursor
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
                'variables' => [
                    'first'  => $pageSize,
                    'after'  => $after,
                    'search' => $search,
                ],
            ];

            $resp = Http::retry(3, 500)->withHeaders([
                'X-Shopify-Access-Token' => $this->token,
                'Content-Type'           => 'application/json',
            ])->post($endpoint, $payload);

            if (!$resp->successful()) {
                throw new \RuntimeException("Shopify HTTP error: {$resp->status()} {$resp->body()}");
            }

            $json = $resp->json();

            if (!empty($json['errors'])) {
                throw new \RuntimeException('Shopify GraphQL errors: ' . json_encode($json['errors']));
            }

            $data = data_get($json, 'data.products');
            if (!$data) {
                throw new \RuntimeException('No products data returned. Check domain/token/scopes (need read_products).');
            }

            $after   = data_get($data, 'pageInfo.endCursor');
            $hasNext = (bool) data_get($data, 'pageInfo.hasNextPage');

            foreach ((array) data_get($data, 'edges', []) as $edge) {
                $p = data_get($edge, 'node');
                $productId = $this->gidToInt(data_get($p, 'id'));

                DB::table('products')->updateOrInsert(
                    ['product_id' => $productId],
                    [
                        'title'        => data_get($p, 'title'),
                        'vendor'       => data_get($p, 'vendor') ?? 'Dayli',
                        'product_type' => data_get($p, 'productType') ?? 'daily-need',
                        'handle'       => data_get($p, 'handle') ?: 'empty-handle',
                        'tags'         => implode(',', (array) data_get($p, 'tags', [])) ?: '""',
                        'status'       => data_get($p, 'status') ?: '""',
                        'img_src'      => data_get($p, 'featuredImage.url') ?: '""',
                        'updated_at'   => now(),
                        'created_at'   => DB::raw('COALESCE(created_at, NOW())'),
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
                            'price'              => (float) data_get($v, 'price', 0),
                            'compare_at_price'   => data_get($v, 'compareAtPrice') !== null
                                ? (float) data_get($v, 'compareAtPrice')
                                : null,
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
}
