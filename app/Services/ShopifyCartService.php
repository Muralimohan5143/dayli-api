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
        $this->shopDomain = config('services.shopify.domain');
        $this->storefrontToken = config('services.shopify.storefront_token');
        $this->apiVersion = config('services.shopify.storefront_api_version', '2025-01');
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

        return $response->json();
    }
}
