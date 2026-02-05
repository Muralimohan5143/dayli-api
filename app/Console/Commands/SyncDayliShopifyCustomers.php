<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SyncDayliShopifyCustomers extends Command
{
    protected $signature = 'dayli:sync-shopify-customers';
    protected $description = 'Sync customers from Shopify into customers table (core first, metafields only when needed)';

    public function handle(): int
    {
        $storeDomain = config('services.shopify_dayli.store_domain', env('SHOPIFY_DAYLI_STORE_DOMAIN'));
        $accessToken = config('services.shopify_dayli.access_token', env('SHOPIFY_DAYLI_ACCESS_TOKEN'));
        $apiVersion  = config('services.shopify_dayli.api_version', env('SHOPIFY_DAYLI_API_VERSION', '2024-10'));

        if (!$storeDomain || !$accessToken) {
            $this->error('Missing SHOPIFY_DAYLI_STORE_DOMAIN or SHOPIFY_DAYLI_ACCESS_TOKEN in .env');
            return self::FAILURE;
        }

        $baseUrl = "https://{$storeDomain}/admin/api/{$apiVersion}/customers.json";

        $this->info("Syncing Shopify customers from {$storeDomain} → customers (core + selective metafields)");
        $pageInfo    = null;
        $totalSynced = 0;

        do {
            try {
                $this->info('Requesting Shopify customers page. page_info=' . ($pageInfo ?? 'NULL'));

                $response = Http::withHeaders([
                    'X-Shopify-Access-Token' => $accessToken,
                    'Content-Type'           => 'application/json',
                    'Accept'                 => 'application/json',
                ])
                    ->timeout(20)
                    ->connectTimeout(5)
                    ->get($baseUrl, array_filter([
                        'limit'  => 20,
                        'page_info' => $pageInfo,
                        'fields' => implode(',', [
                            'id',
                            'first_name',
                            'last_name',
                            'email',
                            'phone',
                            'default_address',
                            'orders_count',
                            'total_spent',
                            'verified_email',
                            'tax_exempt',
                            'locale',
                            'currency',
                            'created_at',
                            'updated_at',
                            'tags',
                            'note',
                        ]),
                    ]));
            } catch (\Throwable $e) {
                $this->error('HTTP exception talking to Shopify: ' . $e->getMessage());
                return self::FAILURE;
            }

            if ($response->failed()) {
                $this->error('Shopify API error: ' . $response->status() . ' ' . $response->body());
                return self::FAILURE;
            }

            $data      = $response->json();
            $customers = $data['customers'] ?? [];

            if (empty($customers)) {
                break;
            }

            $rows = [];

            foreach ($customers as $c) {
                $addr = $c['default_address'] ?? null;

                // --- CORE FIELDS FIRST ---
                $firstNameCore = $c['first_name'] ?? ($addr['first_name'] ?? null);
                $lastNameCore  = $c['last_name']  ?? ($addr['last_name']  ?? null);
                $phoneCore     = $c['phone']      ?? ($addr['phone']      ?? null);
                $emailCore     = $c['email']      ?? null;

                $firstName = $firstNameCore;
                $lastName  = $lastNameCore;
                $phone     = $phoneCore;
                $email     = $emailCore;

                // Decide if we need metafields:
                $needsMetafields =
                    ($firstName === null && $lastName === null && $phone === null && $email === null);

                $meta = [];
                if ($needsMetafields) {
                    $meta = $this->fetchCustomerMetafields(
                        $storeDomain,
                        $apiVersion,
                        $accessToken,
                        $c['id'] ?? null
                    );

                    // Override only if metafield has non-empty value
                    $firstName = $this->pickFirstNonEmpty([
                        $meta['first_name'] ?? null,
                        $firstNameCore,
                    ]);
                    $lastName = $this->pickFirstNonEmpty([
                        $meta['last_name'] ?? null,
                        $lastNameCore,
                    ]);
                    $phone = $this->pickFirstNonEmpty([
                        $meta['phone'] ?? null,
                        $phoneCore,
                    ]);
                    $email = $this->pickFirstNonEmpty([
                        $meta['email'] ?? null,
                        $emailCore,
                    ]);
                }

                // Display name
                $displayName = trim(($firstName ?? '') . ' ' . ($lastName ?? ''));
                if ($displayName === '' && !empty($addr['company'])) {
                    $displayName = $addr['company'];
                }
                if ($displayName === '') {
                    $displayName = null;
                }

                $defaultAddressJson = $addr ? json_encode($addr) : null;

                $rows[] = [
                    'shopify_customer_id' => $c['id'] ?? null,
                    'ops_customer_id'     => null,
                    'zone_id'             => null,

                    'account_status'  => 'active',
                    'origin_system'   => 'dayli',
                    'last_logged_at'  => null,

                    'first_name'      => $firstName,
                    'last_name'       => $lastName,
                    'display_name'    => $displayName,
                    'gender'          => null,

                    'phone'           => $phone,
                    'email'           => $email,

                    // from metafields when present, else address
                    'nagar'     => $this->pickFirstNonEmpty([ $meta['nagar']    ?? null ]),
                    'address'   => $this->pickFirstNonEmpty([ $meta['address']  ?? null, $addr['address1'] ?? null ]),
                    'pincode'   => $this->pickFirstNonEmpty([ $meta['pincode']  ?? null, $addr['zip'] ?? null ]),
                    'zone_code' => $this->pickFirstNonEmpty([ $meta['zone_code'] ?? null ]),

                    'default_address_json' => $defaultAddressJson,
                    'image_url'            => null,
                    'avatar'               => null,

                    'locale'   => $c['locale'] ?? null,
                    'currency' => $c['currency'] ?? null,

                    'verified_email'         => (int)($c['verified_email'] ?? 0),
                    'tax_exempt'             => (int)($c['tax_exempt'] ?? 0),
                    'marketing_opt_in_level' => null,

                    'number_of_orders' => $c['orders_count'] ?? 0,
                    'amount_spent'     => $c['total_spent'] ?? 0.00,
                    'total_amount_due' => 0.00,

                    'tags' => isset($c['tags'])
                        ? (is_string($c['tags']) ? $c['tags'] : implode(',', (array) $c['tags']))
                        : null,
                    'note' => $c['note'] ?? null,
                    'bio'  => null,

                    'originating_from'       => 'shopify_dayli',
                    'should_sync_with'       => null,
                    'sync_completed_with'    => null,
                    'profile_metaobject_gid' => null,

                    'skills' => null,

                    'shopify_created_at' => $c['created_at'] ?? null,
                    'shopify_updated_at' => $c['updated_at'] ?? null,

                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ];
            }

            if (!empty($rows)) {
                DB::table('users')->upsert(
                    $rows,
                    ['shopify_customer_id'],
                    [
                        'ops_customer_id',
                        'zone_id',
                        'account_status',
                        'origin_system',
                        'last_logged_at',
                        'first_name',
                        'last_name',
                        'display_name',
                        'gender',
                        'phone',
                        'email',
                        'nagar',
                        'address',
                        'pincode',
                        'zone_code',
                        'default_address_json',
                        'image_url',
                        'avatar',
                        'locale',
                        'currency',
                        'verified_email',
                        'tax_exempt',
                        'marketing_opt_in_level',
                        'number_of_orders',
                        'amount_spent',
                        'total_amount_due',
                        'tags',
                        'note',
                        'bio',
                        'originating_from',
                        'should_sync_with',
                        'sync_completed_with',
                        'profile_metaobject_gid',
                        'skills',
                        'shopify_created_at',
                        'shopify_updated_at',
                        'updated_at',
                        'deleted_at',
                    ]
                );

                $count       = count($rows);
                $totalSynced += $count;
                $this->info("Synced batch of {$count}, total so far: {$totalSynced}");
            }

            $linkHeader = $response->header('Link');
            $pageInfo   = $this->extractNextPageInfo($linkHeader);
        } while ($pageInfo !== null);

        $this->info("Finished syncing. Total customers processed: {$totalSynced}");

        return self::SUCCESS;
    }

    private function pickFirstNonEmpty(array $candidates): ?string
    {
        foreach ($candidates as $v) {
            if ($v === null) {
                continue;
            }
            $str = is_string($v) ? trim($v) : $v;
            if ($str !== '') {
                return $str;
            }
        }
        return null;
    }

    private function fetchCustomerMetafields(string $storeDomain, string $apiVersion, string $accessToken, ?int $customerId): array
    {
        if (!$customerId) {
            return [];
        }

        $namespace = 'custom';
        $url = "https://{$storeDomain}/admin/api/{$apiVersion}/customers/{$customerId}/metafields.json";

        try {
            $response = Http::withHeaders([
                'X-Shopify-Access-Token' => $accessToken,
                'Content-Type'           => 'application/json',
                'Accept'                 => 'application/json',
            ])
                ->timeout(10)
                ->connectTimeout(5)
                ->get($url, [
                    'namespace' => $namespace,
                    'limit'     => 50,
                ]);
        } catch (\Throwable $e) {
            $this->warn("Metafields HTTP exception for customer {$customerId}: " . $e->getMessage());
            return [];
        }

        if ($response->failed()) {
            $this->warn("Metafields fetch failed for customer {$customerId}: " . $response->status());
            return [];
        }

        $metafields = $response->json('metafields', []);

        $out = [];
        foreach ($metafields as $mf) {
            $key   = $mf['key'] ?? null;
            $value = $mf['value'] ?? null;
            if (!$key) {
                continue;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    private function extractNextPageInfo(?string $linkHeader): ?string
    {
        if (!$linkHeader) {
            return null;
        }

        $parts = explode(',', $linkHeader);
        foreach ($parts as $part) {
            if (str_contains($part, 'rel="next"')) {
                if (preg_match('/<([^>]+)>/', $part, $matches)) {
                    $url = $matches[1] ?? null;
                    if ($url && parse_url($url, PHP_URL_QUERY)) {
                        parse_str(parse_url($url, PHP_URL_QUERY), $query);
                        return $query['page_info'] ?? null;
                    }
                }
            }
        }

        return null;
    }
}
