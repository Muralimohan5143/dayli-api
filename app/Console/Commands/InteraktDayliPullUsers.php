<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\ProviderAccount;
use App\Services\Interakt\InteraktClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\DuplicateContactsFound;
use App\Support\PhoneNormalizer;


class InteraktDayliPullUsers extends Command
{
    use PhoneNormalizer;

    protected $signature = 'interakt:dayli-pull-users 
        {account=dayli : Account code, e.g. dayli|leela}
        {--since= : ISO date for incremental sync (e.g. 2025-10-01)}
        {--limit=100 : Page size per API request}
        {--dry : Dry-run (fetch + map + show only, no DB writes)}';

    protected $description = 'Fetch Interakt users and upsert into customers, reporting duplicates and optionally dry-running.';

    public function handle(InteraktClient $api): int
    {
        $account = (string)$this->argument('account');
        $limit   = (int)$this->option('limit');
        $dry     = (bool)$this->option('dry');
        $offset  = 0;

        // Ensure provider account exists
        $pa = ProviderAccount::firstOrCreate(
            ['provider' => 'interakt', 'account_code' => $account],
            ['display_name' => ucfirst($account) . ' Interakt', 'is_active' => true]
        );

        // Compute baseline timestamp
        $last = CustomerIdentity::whereHas('providerAccount', function ($q) use ($account) {
            $q->where('provider', 'interakt')->where('account_code', $account);
        })->max('last_synced_at');

        $since = $this->option('since') ?: optional($last)?->toIso8601String() ?: '2000-01-01';

        $filters = [
            'filters' => [[
                'trait' => 'modified_at_utc',
                'op'    => 'gt',
                'val'   => $since,
            ]],
        ];

        $total = 0;
        $duplicates = [];

        $this->info("Syncing Interakt '{$account}' contacts since {$since}...");
        if ($dry) $this->warn('⚠️  Dry-run mode: No data will be written to DB.');

        do {
            $resp = $api->listUsers($offset, $limit, $filters);

            $data      = is_array($resp['data'] ?? null) ? $resp['data'] : [];
            $customers = is_array($data['customers'] ?? null) ? $data['customers'] : [];
            $hasNext   = (bool)($data['has_next_page'] ?? false);

            $count = count($customers);
            $this->line("Fetched batch @offset {$offset}: {$count} users");

            foreach ($customers as $u) {
                $interaktId  = $u['id'] ?? null;
                $createdUtc  = $u['created_at_utc'] ?? null;
                $modifiedUtc = $u['modified_at_utc'] ?? null;
                $rawPhone    = $u['phone_number'] ?? null;
                $jid         = $u['channel_account_identifier'] ?? null;
                $cc          = $u['country_code'] ?? '+91';
                $traits      = $u['traits'] ?? [];
                $name        = $traits['name'] ?? null;

                // Normalize phone
                $phone = $this->normPhone($rawPhone ?: $jid, $cc);
                [$first, $last] = $this->splitName($name);

                // Build natural key
                $natural = $this->buildNaturalKey($phone, null, [], $first, $last);

                // Detect duplicates
                $probe = $this->probeDuplicates($phone, null, $natural);
                if (!empty($probe['conflict_ids'])) {
                    $duplicates[] = [
                        'origin'              => "interakt:{$account}",
                        'source_id'           => (string)($interaktId ?? ''),
                        'name'                => $name ?? null,
                        'phone'               => $phone,
                        'pincode'             => null,
                        'email'               => null,
                        'natural_key'         => $natural,
                        'matched_customer_id' => $probe['match_id'],
                        'conflict_ids'        => $probe['conflict_ids'],
                    ];
                }

                if ($dry) {
                    $this->line(sprintf(
                        '• %s | %s | %s',
                        $interaktId ?? '-',
                        $name ?? '-',
                        $phone ?? '-'
                    ));
                    $total++;
                    continue;
                }

                // --- UPSERT LOGIC ---
                $lookup = $phone
                    ? ['phone' => $phone]
                    : ['interakt_contact_id' => $interaktId];

                $attributes = [
                    'display_name'            => $name,
                    'first_name'              => $first,
                    'last_name'               => $last,
                    'interakt_contact_id'     => $interaktId,
                    'locale'                  => $traits['locale'] ?? null,
                    'tags'                    => null,
                    'last_synced_interakt_at' => now(),
                    'external_refs'           => [
                        'interakt' => [
                            'created_at_utc'  => $createdUtc,
                            'modified_at_utc' => $modifiedUtc,
                            'channel_type'    => $u['channel_type'] ?? null,
                            'channel_account_identifier' => $jid,
                        ],
                    ],
                ];

                $cust = Customer::updateOrCreate($lookup, $attributes);

                // Attach identity link
                if ($interaktId) {
                    CustomerIdentity::updateOrCreate(
                        [
                            'customer_id'         => $cust->id,
                            'provider_account_id' => $pa->id,
                            'external_id'         => (string)$interaktId,
                        ],
                        [
                            'status'         => 'active',
                            'last_synced_at' => now(),
                            'meta'           => ['channel_type' => $u['channel_type'] ?? null],
                        ]
                    );
                }

                $total++;
            }

            $offset += $limit;
        } while ($hasNext);

        if (!$dry && count($duplicates)) {
            Mail::to('admin@leelashop.in')->send(
                new DuplicateContactsFound("interakt:{$account}", $duplicates)
            );
        }

        $this->info(
            $dry
                ? "Dry-run complete. Previewed {$total} contacts."
                : "Done. Pulled/updated {$total} contacts."
        );

        return self::SUCCESS;
    }

    /* ================= helpers ================ */

    protected function splitName(?string $name): array
    {
        if (!$name) return [null, null];
        $trim = trim(preg_replace('/\s+/', ' ', $name));
        if ($trim === '') return [null, null];
        $parts = explode(' ', $trim, 2);
        return [$parts[0] ?? null, $parts[1] ?? null];
    }

    protected function buildNaturalKey(?string $phone, ?string $email, array $addr = [], ?string $first = null, ?string $last = null): string
    {
        $phone = $this->normPhone($phone);
        if ($phone) return strtolower(preg_replace('/[^0-9+]/', '', $phone));
        if ($email) return strtolower($email);
        return hash('sha256', implode('|', [(string)$first, (string)$last]));
    }

    protected function probeDuplicates(?string $phone, ?string $email, string $natural): array
    {
        $ids = [];

        if ($phone) {
            $id = Customer::where('phone', $phone)->value('id');
            if ($id) $ids[] = $id;
        }
        if ($email) {
            $id = Customer::where('email', strtolower($email))->value('id');
            if ($id) $ids[] = $id;
        }
        $id = Customer::where('natural_key', $natural)->value('id');
        if ($id) $ids[] = $id;

        $ids = array_values(array_unique(array_filter($ids)));
        if (empty($ids)) return ['match_id' => null, 'conflict_ids' => []];
        if (count($ids) === 1) return ['match_id' => $ids[0], 'conflict_ids' => []];

        return ['match_id' => $ids[0], 'conflict_ids' => array_slice($ids, 1)];
    }
}
