<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class BackfillOrderMetafields extends Command
{
    protected $signature = 'dayli:backfill-order-metafields';
    protected $description = 'Copy customer metafields (nagar, address) into order metafields';

    protected ?string $endpoint = null;
    protected ?string $token = null;
    // 🔒 CHANGE ONLY IF YOUR NAMESPACE IS DIFFERENT
    protected string $namespace = 'custom';

    public function __construct()
    {
        parent::__construct();
    }



    public function handle(): int
    {
        $store   = config('services.shopify_dayli.store_domain');
        $version = config('services.shopify_dayli.api_version', '2024-01');
        $this->token = config('services.shopify_dayli.access_token');

        if (!$store || !$this->token) {
            $this->error('Missing Shopify config: services.shopify_dayli.store_domain or services.shopify_dayli.access_token');
            return self::FAILURE;
        }

        $this->endpoint = "https://{$store}/admin/api/{$version}/graphql.json";

        $this->info('Starting order metafield backfill…');

        // sanity check
        $ping = $this->graphql('query { shop { name myshopifyDomain } }');
        $this->info('Shop: ' . json_encode(data_get($ping, 'data.shop')));

        $cursor = null;

        $seen = 0;
        $updated = 0;
        $skippedNoCustomer = 0;
        $skippedAlreadySet = 0;
        $skippedNoValues = 0;

        do {
            [$orders, $cursor] = $this->fetchOrders($cursor);

            foreach ($orders as $order) {
                $seen++;

                $customerId = data_get($order, 'customer.id');
                if (!$customerId) {
                    $skippedNoCustomer++;
                    continue;
                }

                // ✅ skip if order already has nagar/address metafields
                $already = $this->fetchOrderMetafields($order['id']);
                $hasNagar = !empty($already['nagar']);
                $hasAddr  = !empty($already['address']);
                if ($hasNagar || $hasAddr) {
                    $skippedAlreadySet++;
                    continue;
                }

                [$nagar, $address] = $this->fetchCustomerMetafields($customerId);

                if (empty($nagar) && empty($address)) {
                    $skippedNoValues++;
                    continue;
                }

                $ok = $this->setOrderMetafields($order['id'], $nagar, $address);
                if ($ok) $updated++;
            }

            $this->info("Progress: seen={$seen}, updated={$updated}, cursor=" . ($cursor ? 'yes' : 'no'));
        } while ($cursor);

        $this->info("Done ✅  seen={$seen}, updated={$updated}, skippedNoCustomer={$skippedNoCustomer}, skippedAlreadySet={$skippedAlreadySet}, skippedNoValues={$skippedNoValues}");

        return self::SUCCESS;
    }


    protected function ordersQuery(): string
    {
        return <<<GQL
query {
  orders(first: 50, query: "status:any") {
    edges {
      node {
        id
        name
        customer { id }
      }
    }
  }
}
GQL;
    }


    // --------------------------------------------------

    protected function fetchOrders(?string $cursor)
    {
        $after = $cursor ? ", after: \"{$cursor}\"" : "";

        $query = <<<GQL
query {
  orders(first: 100{$after}, query: "status:any", sortKey: CREATED_AT, reverse: true) {
    pageInfo {
      hasNextPage
      endCursor
    }
    edges {
      node {
        id
        name
        customer { id }
      }
    }
  }
}
GQL;

        $res = $this->graphql($query);

        $nodes = [];
        $edges = data_get($res, 'data.orders.edges', []);
        foreach ($edges as $e) {
            $nodes[] = $e['node'];
        }

        $hasNext = (bool) data_get($res, 'data.orders.pageInfo.hasNextPage');
        $endCursor = data_get($res, 'data.orders.pageInfo.endCursor');

        return [$nodes, $hasNext ? $endCursor : null];
    }



    protected function fetchOrderMetafields(string $orderId): array
    {
        $query = <<<GQL
query {
  order(id: "{$orderId}") {
    nagar: metafield(namespace: "{$this->namespace}", key: "nagar") { value }
    address: metafield(namespace: "{$this->namespace}", key: "address") { value }
  }
}
GQL;

        $res = $this->graphql($query);

        return [
            'nagar'   => data_get($res, 'data.order.nagar.value'),
            'address' => data_get($res, 'data.order.address.value'),
        ];
    }
    protected function processOrder(array $order)
    {
        if (empty($order['customer']['id'])) {
            return;
        }

        $customerId = $order['customer']['id'];
        $orderId = $order['id'];

        [$nagar, $address] = $this->fetchCustomerMetafields($customerId);

        if (!$nagar && !$address) {
            return;
        }

        $this->setOrderMetafields($orderId, $nagar, $address);
    }

    protected function fetchCustomerMetafields(string $customerId): array
    {
        $query = <<<GQL
query {
  customer(id: "{$customerId}") {
    nagar: metafield(namespace: "{$this->namespace}", key: "nagar") {
      value
    }
    address: metafield(namespace: "{$this->namespace}", key: "address") {
      value
    }
  }
}
GQL;

        $res = $this->graphql($query);

        return [
            data_get($res, 'data.customer.nagar.value'),
            data_get($res, 'data.customer.address.value'),
        ];
    }

    protected function setOrderMetafields(string $orderId, ?string $nagar, ?string $address): bool
    {
        $fields = [];

        if (!empty($nagar)) {
            $fields[] = [
                'ownerId' => $orderId,
                'namespace' => $this->namespace,
                'key' => 'nagar',
                'type' => 'single_line_text_field',
                'value' => $nagar,
            ];
        }

        if (!empty($address)) {
            $fields[] = [
                'ownerId' => $orderId,
                'namespace' => $this->namespace,
                'key' => 'address',
                'type' => 'single_line_text_field',
                'value' => $address,
            ];
        }

        if (empty($fields)) return false;

        $mutation = <<<GQL
mutation(\$metafields: [MetafieldsSetInput!]!) {
  metafieldsSet(metafields: \$metafields) {
    userErrors { field message }
  }
}
GQL;

        // ✅ This query uses variables, so pass variables array (non-empty)
        $res = $this->graphql($mutation, ['metafields' => $fields]);

        $errs = data_get($res, 'data.metafieldsSet.userErrors', []);
        if (!empty($errs)) {
            $this->error("metafieldsSet error: " . json_encode($errs));
            return false;
        }

        return true;
    }


    protected function graphql(string $query, array $variables = null)
    {
        if (!$this->endpoint || !$this->token) {
            throw new \RuntimeException('Endpoint/token not initialized (handle() did not set them).');
        }
        $payload = ['query' => $query];

        if ($variables !== null && !empty($variables)) {
            $payload['variables'] = $variables;
        }

        $res = \Illuminate\Support\Facades\Http::withHeaders([
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ])->post($this->endpoint, $payload);

        $json = $res->json();

        if (!empty($json['errors'])) {
            $this->error("Shopify GraphQL errors:");
            $this->line(json_encode($json['errors'], JSON_PRETTY_PRINT));
        }

        return $json;
    }
}
