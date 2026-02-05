<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Log;

class ShopifyMetafieldService
{
    protected string $store;
    protected string $token;
    protected string $apiVersion = '2025-07';

    public function __construct()
    {
        $this->store = config('shopify.domain', 'ce1cd1.myshopify.com');
        $this->token = config('shopify.token', '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');
    }

    protected function graphql(string $query, array $variables = []): array
    {
        $url = "https://{$this->store}/admin/api/{$this->apiVersion}/graphql.json";

        $response = Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->post($url, [
            'query' => $query,
            'variables' => $variables,
        ]);

        if ($response->failed()) {
            throw new \Exception("GraphQL request failed: {$response->status()} - {$response->body()}");
        }

        return $response->json();
    }

    protected function productIdByHandle(string $handle): ?string
    {
        $query = <<<'GQL'
        query($handle: String!) {
          productByHandle(handle: $handle) {
            id
          }
        }
        GQL;

        $data = $this->graphql($query, ['handle' => $handle]);

        return $data['data']['productByHandle']['id'] ?? null;
    }

    protected function setMetafields(string $productId, string $type, string $subtype): array
    {
        $mutation = <<<'GQL'
        mutation setMeta($ownerId: ID!, $typeVal: String!, $subtypeVal: String!) {
          metafieldsSet(metafields: [
            { ownerId: $ownerId, namespace: "custom", key: "type", type: "single_line_text_field", value: $typeVal },
            { ownerId: $ownerId, namespace: "custom", key: "subtype", type: "single_line_text_field", value: $subtypeVal }
          ]) {
            userErrors { field message }
          }
        }
        GQL;

        $data = $this->graphql($mutation, [
            'ownerId'   => $productId,
            'typeVal'   => $type,
            'subtypeVal'=> $subtype,
        ]);

        return $data['data']['metafieldsSet']['userErrors'] ?? [];
    }

    public function pushFromExcel(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        // Expect header row
        $header = array_map('strtolower', $rows[1]);
        $handleCol = array_search('handle', $header);
        $typeCol = array_search('type', $header);
        $subtypeCol = array_search('subtype', $header);

        if ($handleCol === false || $typeCol === false || $subtypeCol === false) {
            throw new \Exception("Excel must have columns: handle, type, subtype");
        }

        $results = [];

        foreach ($rows as $i => $row) {
            if ($i === 1) continue; // skip header

            $handle = trim($row[$handleCol] ?? '');
            $type   = trim($row[$typeCol] ?? '');
            $subtype= trim($row[$subtypeCol] ?? '');

            if (!$handle) continue;

            $status = 'ok';
            $detail = '';
            $gid = null;

            try {
                $gid = $this->productIdByHandle($handle);
                if (!$gid) {
                    $status = 'not_found';
                    $detail = 'No product found';
                } else {
                    $errors = $this->setMetafields($gid, $type, $subtype);
                    if ($errors) {
                        $status = 'user_errors';
                        $detail = json_encode($errors);
                    }
                }
            } catch (\Throwable $e) {
                $status = 'error';
                $detail = $e->getMessage();
            }

            $results[] = compact('handle', 'type', 'subtype', 'gid', 'status', 'detail');

            Log::info("Shopify metafield push", end($results));
        }

        return $results;
    }
}
