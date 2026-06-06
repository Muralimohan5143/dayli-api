<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class ShopifyCartService
{
    protected string $shopDomain;
    protected string $storefrontToken;
    protected string $apiVersion;

    public function __construct()
    {
        $this->shopDomain = config('services.shopify_dayli.store_domain');
        $this->storefrontToken = config('services.shopify_dayli.storefront_token');
        $this->apiVersion = config('services.shopify_dayli.api_version', '2025-07');
    }

    public function createCart(array $lines): array
    {
        $cartLines = collect($lines)->map(function ($line) {
            return [
                'merchandiseId' => 'gid://shopify/ProductVariant/' . $line['variant_id'],
                'quantity' => (int) $line['qty'],
            ];
        })->values()->all();

        $query = <<<'GRAPHQL'
mutation cartCreate($input: CartInput!) {
  cartCreate(input: $input) {
    cart {
      id
      checkoutUrl
    }
    userErrors {
      field
      message
    }
  }
}
GRAPHQL;

        $response = $this->graphql($query, [
            'input' => [
                'lines' => $cartLines,
            ],
        ]);

        $errors = $response['data']['cartCreate']['userErrors'] ?? [];

        if (!empty($errors)) {
            abort(422, $errors[0]['message'] ?? 'Shopify cart create failed');
        }

        return $response['data']['cartCreate']['cart'] ?? [];
    }

    protected function graphql(string $query, array $variables = []): array
    {
        $url = "https://{$this->shopDomain}/api/{$this->apiVersion}/graphql.json";

        $response = Http::withHeaders([
            'X-Shopify-Storefront-Access-Token' => $this->storefrontToken,
            'Content-Type' => 'application/json',
        ])->post($url, [
            'query' => $query,
            'variables' => $variables,
        ]);

        if (!$response->successful()) {
            abort(500, 'Shopify API failed: ' . $response->body());
        }

        return $response->json();
    }
}
