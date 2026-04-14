<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SeedVendorMilkSupply extends Command
{

    // file for generate vendor milk supply data for Vishnu and Bhaskar into roles, user_services, SCR, draft_orders, and DOI
    protected $signature = 'dayli:seed-vendor-milk-supply
                        {--dry-run : Show what would be inserted/updated without saving}
                        {--from=2026-04-01 : Start date}
                        {--to=2026-04-07 : End date}
                        {--log-file= : Custom log file path inside storage/logs}';

    protected $description = 'Seed vendor milk supply data for Vishnu and Bhaskar into roles, user_services, SCR, draft_orders, and DOI';

    private bool $dryRun = false;
    private string $startDate;
    private string $endDate;
    private string $today;
    private string $logChannel = 'daily';

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->startDate = (string) $this->option('from');
        $this->endDate = (string) $this->option('to');
        $this->today = Carbon::today()->toDateString();

        $customLogFile = $this->option('log-file');
        if ($customLogFile) {
            config([
                'logging.channels.vendor_seed_custom' => [
                    'driver' => 'single',
                    'path' => storage_path('logs/' . ltrim($customLogFile, '/\\')),
                    'level' => 'debug',
                ],
            ]);
            $this->logChannel = 'vendor_seed_custom';
        }

        $this->log('START vendor seed', [
            'dry_run' => $this->dryRun,
            'from' => $this->startDate,
            'today' => $this->today,
        ]);

        $vendorRoleId = 5;
        $vendorMilkRoleId = 6;
        $subscriptionTypeId = 3;
        $zoneId = 1;

        $vendors = [
            [
                'user_id' => 11346,
                'name' => 'Bhasker sir',
                'service_handle' => 'milk-supplier',
                'title' => 'Bhaskar Milk Supply',
                'daily_items' => [
                    '2026-04-01' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-02' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 5.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-03' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 2.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-04' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 2.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-05' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-06' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 0.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                    '2026-04-07' => [
                        ['product_id' => 8383403917586, 'variant_id' => 45490819596562, 'label' => 'Arokya Gold(500 ml)', 'qty' => 4.00, 'price' => 40.00],
                        ['product_id' => 8409961103634, 'variant_id' => 45560024826130, 'label' => 'Hatsun Curd (Big, 400 g)', 'qty' => 1.00, 'price' => 40.00],
                        ['product_id' => 10288980754706, 'variant_id' => 52149601829138, 'label' => 'Hatsun Curd (Small, 110 g)', 'qty' => 1.00, 'price' => 10.00],
                    ],
                ],
            ],
            [
                'user_id' => 11345,
                'name' => 'Vishnu Vardhan Redyy',
                'service_handle' => 'milk-supplier',
                'title' => 'Vishnu Milk Supply',
                'daily_items' => [
                    '2026-04-01' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 31.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 6.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                    ],
                    '2026-04-02' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 6.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 2.00, 'price' => 35.00],
                    ],
                    '2026-04-03' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 35.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 8.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                    ],
                    '2026-04-04' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 34.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                    ],
                    '2026-04-05' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 32.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                    ],
                    '2026-04-06' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 36.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                    ],
                    '2026-04-07' => [
                        ['product_id' => 8383403720978, 'variant_id' => 52149488976146, 'label' => 'Vijaya Gold Milk(500 ml)', 'qty' => 30.00, 'price' => 37.00],
                        ['product_id' => 8425366782226, 'variant_id' => 45623554146578, 'label' => 'Vijaya Toned Milk(500ml)', 'qty' => 9.00, 'price' => 30.00],
                        ['product_id' => 8425366782226, 'variant_id' => 52148028047634, 'label' => 'Vijaya Toned Milk Small', 'qty' => 1.00, 'price' => 10.00],
                        ['product_id' => 8421025218834, 'variant_id' => 45608528314642, 'label' => 'Vijaya Curd(500 ml)', 'qty' => 1.00, 'price' => 35.00],
                    ],
                ],
            ],
        ];

        foreach ($vendors as $vendor) {
            $this->line('');
            $this->info("Processing vendor: {$vendor['name']} ({$vendor['user_id']})");

            DB::beginTransaction();

            try {
                $this->seedRoles($vendor['user_id'], $vendorRoleId, $vendorMilkRoleId);

                $this->seedUserService(
                    userId: $vendor['user_id'],
                    serviceHandle: $vendor['service_handle'],
                    subscriptionTypeId: $subscriptionTypeId,
                    zoneId: $zoneId
                );

                $scrId = $this->seedScr(
                    userId: $vendor['user_id'],
                    subscriptionTypeId: $subscriptionTypeId,
                    zoneId: $zoneId
                );

                $draftOrderId = $this->seedDraftOrder(
                    scrId: $scrId,
                    vendorId: $vendor['user_id'],
                    zoneId: $zoneId,
                    title: $vendor['title']
                );

                $this->linkScrToDraftOrder($scrId, $draftOrderId);

                foreach ($vendor['daily_items'] as $supplyDate => $items) {
                    foreach ($items as $item) {
                        $this->seedDoiForDate(
                            draftOrderId: $draftOrderId,
                            vendorId: $vendor['user_id'],
                            productId: $item['product_id'],
                            variantId: $item['variant_id'],
                            qty: $item['qty'],
                            price: $item['price'],
                            label: $item['label'],
                            supplyDate: $supplyDate
                        );
                    }
                }

                if ($this->dryRun) {
                    DB::rollBack();
                    $this->warn("DRY RUN: rolled back {$vendor['name']}");
                    $this->log('DRY RUN rollback', ['vendor_id' => $vendor['user_id']]);
                } else {
                    DB::commit();
                    $this->info("Committed: {$vendor['name']}");
                    $this->log('Committed vendor seed', ['vendor_id' => $vendor['user_id']]);
                }
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Failed for {$vendor['name']}: " . $e->getMessage());
                $this->log('FAILED vendor seed', [
                    'vendor_id' => $vendor['user_id'],
                    'error' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        $this->line('');
        $this->info($this->dryRun ? 'Dry run completed.' : 'Vendor seed completed.');
        $this->log('END vendor seed', ['dry_run' => $this->dryRun]);

        return self::SUCCESS;
    }

    private function seedRoles(int $userId, int $vendorRoleId, int $vendorMilkRoleId): void
    {
        foreach ([$vendorRoleId, $vendorMilkRoleId] as $roleId) {
            $exists = DB::table('model_has_roles')
                ->where('role_id', $roleId)
                ->where('model_type', 'App\\Models\\User')
                ->where('model_id', $userId)
                ->exists();

            if ($exists) {
                $this->line("  role exists: role_id={$roleId}");
                $this->log('role exists', compact('userId', 'roleId'));
                continue;
            }

            $this->line("  role insert: role_id={$roleId}");
            $this->log('role insert', compact('userId', 'roleId'));

            if (! $this->dryRun) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $roleId,
                    'model_type' => 'App\\Models\\User',
                    'model_id' => $userId,
                ]);
            }
        }
    }

    private function seedUserService(
        int $userId,
        string $serviceHandle,
        int $subscriptionTypeId,
        int $zoneId
    ): void {
        $existing = DB::table('user_services')
            ->where('user_id', $userId)
            ->where('role_name', 'vendor')
            ->where('service_handle', $serviceHandle)
            ->where('subscription_type_id', $subscriptionTypeId)
            ->where('zone_id', $zoneId)
            ->first();

        $payload = [
            'status' => 'approved',
            'is_active' => 1,
            'approved_by' => null,
            'approved_at' => now(),
            'rejection_reason' => null,
            'meta' => json_encode([
                'seeded' => true,
                'seed_type' => 'vendor_supply',
            ]),
            'updated_at' => now(),
        ];

        if ($existing) {
            $row = [
                'id' => $existing->id,
                'user_id' => $userId,
                'role_name' => 'vendor',
                'service_handle' => $serviceHandle,
                'subscription_type_id' => $subscriptionTypeId,
                'zone_id' => $zoneId,
                ...$payload,
            ];

            $this->line("  user_service update: id={$existing->id}");
            $this->log('user_service update full', $row);

            if (! $this->dryRun) {
                DB::table('user_services')->where('id', $existing->id)->update($payload);
            }
            return;
        }

        $row = [
            'user_id' => $userId,
            'role_name' => 'vendor',
            'service_handle' => $serviceHandle,
            'subscription_type_id' => $subscriptionTypeId,
            'zone_id' => $zoneId,
            ...$payload,
            'created_at' => now(),
        ];

        $this->line("  user_service insert: user_id={$userId}");
        $this->log('user_service insert full', $row);

        if (! $this->dryRun) {
            DB::table('user_services')->insert($row);
        }
    }

    private function seedScr(int $userId, int $subscriptionTypeId, int $zoneId): int
    {
        $existing = DB::table('sub_change_requests')
            ->where('for_user_id', $userId)
            ->where('by_user_id', $userId)
            ->where('party_type', 'supplier')
            ->where('subscription_type_id', $subscriptionTypeId)
            ->where('zone_id', $zoneId)
            ->where('action', 'create')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $this->line("  SCR exists/update: id={$existing->id}");
            $this->log('scr update', ['id' => $existing->id, 'user_id' => $userId]);

            $updateRow = [
                'status' => 'approved',
                'approved_at' => now(),
                'invoice_cycle' => 'monthly',
                'meta' => json_encode([
                    'seeded' => true,
                    'seed_type' => 'vendor_supply',
                    'start_date' => $this->startDate,
                    'seeded_till' => $this->today,
                ]),
                'updated_at' => now(),
            ];

            $this->log('scr update full', [
                'id' => $existing->id,
                ...$updateRow,
            ]);

            if (! $this->dryRun) {
                DB::table('sub_change_requests')
                    ->where('id', $existing->id)
                    ->update($updateRow);
            }
            return (int) $existing->id;
        }

        $this->line("  SCR insert: user_id={$userId}");
        $this->log('scr insert', ['user_id' => $userId]);

        $row = [
            'for_user_id' => $userId,
            'by_user_id' => $userId,
            'party_type' => 'supplier',
            'from_id' => null,
            'draft_order_id' => null,
            'zone_id' => $zoneId,
            'subscription_type_id' => $subscriptionTypeId,
            'subtypes_json' => null,
            'custom_frequency_format' => null,
            'invoice_cycle' => 'monthly',
            'change_reason' => 'self_service',
            'action' => 'create',
            'status' => 'approved',
            'approved_by' => null,
            'approved_at' => now(),
            'priority' => 3,
            'payload' => json_encode([
                'seeded_for' => 'vendor_supply',
                'start_date' => $this->startDate,
                'seeded_till' => $this->today,
            ]),
            'meta' => json_encode([
                'seeded' => true,
                'seed_type' => 'vendor_supply',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->log('scr insert full', $row);

        if ($this->dryRun) {
            return -$userId;
        }

        return (int) DB::table('sub_change_requests')->insertGetId($row);
    }

    private function seedDraftOrder(int $scrId, int $vendorId, int $zoneId, string $title): int
    {
        $existing = DB::table('draft_orders')
            ->where('change_request_id', $scrId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            $this->line("  draft_order update: id={$existing->id}");

            $updateRow = [
                'vendor_id' => $vendorId,
                'zone_id' => $zoneId,
                'cadence' => 'daily',
                'invoice_cycle' => 'monthly',
                'start_date' => $this->startDate,
                'end_date' => $this->endDate,
                'timezone' => 'Asia/Kolkata',
                'title' => $title,
                'meta' => json_encode([
                    'seeded' => true,
                    'party_type' => 'supplier',
                ]),
                'updated_at' => now(),
            ];

            $this->log('draft_order update full', [
                'id' => $existing->id,
                ...$updateRow,
            ]);

            if (! $this->dryRun) {
                DB::table('draft_orders')
                    ->where('id', $existing->id)
                    ->update($updateRow);
            }

            return (int) $existing->id;
        }

        $this->line("  draft_order insert: vendor_id={$vendorId}");

        $row = [
            'change_request_id' => $scrId,
            'customer_id' => null,
            'vendor_id' => $vendorId,
            'zone_id' => $zoneId,
            'cadence' => 'daily',
            'custom_frequency_format' => null,
            'invoice_cycle' => 'monthly',
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => 'active',
            'locked_at' => null,
            'timezone' => 'Asia/Kolkata',
            'title' => $title,
            'pricing_policy' => null,
            'tax_policy' => null,
            'meta' => json_encode([
                'seeded' => true,
                'party_type' => 'supplier',
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $this->log('draft_order insert full', $row);

        if ($this->dryRun) {
            return - ($vendorId + 100000);
        }

        return (int) DB::table('draft_orders')->insertGetId($row);
    }
    private function linkScrToDraftOrder(int $scrId, int $draftOrderId): void
    {
        $this->line("  SCR link draft_order_id={$draftOrderId}");
        $this->log('scr link draft order', compact('scrId', 'draftOrderId'));

        if (! $this->dryRun && $scrId > 0 && $draftOrderId > 0) {
            DB::table('sub_change_requests')
                ->where('id', $scrId)
                ->update([
                    'draft_order_id' => $draftOrderId,
                    'updated_at' => now(),
                ]);
        }
    }

    private function seedDoi(
        int $draftOrderId,
        int $vendorId,
        int $productId,
        int $variantId,
        float $qty,
        float $price,
        string $label
    ): void {
        $existing = DB::table('draft_order_items')
            ->where('draft_order_id', $draftOrderId)
            ->where('variant_id', $variantId)
            ->where('vendor_id', $vendorId)
            ->where('start_date', $this->startDate)
            ->first();

        $payload = [
            'original_item_id' => null,
            'change_action' => 'create',
            'draft_order_id' => $draftOrderId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'vendor_id' => $vendorId,
            'frequency_type' => $qty > 0 ? 'daily' : null,
            'qty' => $qty,
            'unit' => 'pcs',
            'price_snapshot' => $price,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'status' => $qty > 0 ? 'active' : 'paused',
            'supersedes_doi_id' => null,
            'created_from_action' => 'vendor_seed',
            'closed_by_action' => null,
            'meta' => json_encode([
                'seeded_for' => 'vendor_supply',
                'label' => $label,
                'is_zero_qty_seed' => $qty == 0,
            ]),
            'updated_at' => now(),
        ];

        $this->log('doi payload full', $payload);

        if ($existing) {
            $this->line("  DOI update: variant_id={$variantId}, qty={$qty}");
            $this->log('doi update full', [
                'id' => $existing->id,
                ...$payload,
            ]);

            if (! $this->dryRun) {
                DB::table('draft_order_items')->where('id', $existing->id)->update($payload);
            }
            return;
        }

        $this->line("  DOI insert: variant_id={$variantId}, qty={$qty}");
        $this->log('doi insert full', $payload);

        if (! $this->dryRun) {
            DB::table('draft_order_items')->insert([
                ...$payload,
                'created_at' => now(),
            ]);
        }
    }

    private function seedDoiForDate(
        int $draftOrderId,
        int $vendorId,
        int $productId,
        int $variantId,
        float $qty,
        float $price,
        string $label,
        string $supplyDate
    ): void {
        $existing = DB::table('draft_order_items')
            ->where('draft_order_id', $draftOrderId)
            ->where('variant_id', $variantId)
            ->where('vendor_id', $vendorId)
            ->where('start_date', $supplyDate)
            ->first();

        $payload = [
            'original_item_id' => null,
            'change_action' => 'create',
            'draft_order_id' => $draftOrderId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'vendor_id' => $vendorId,
            'frequency_type' => $qty > 0 ? 'daily' : null,
            'qty' => $qty,
            'unit' => 'pcs',
            'price_snapshot' => $price,
            'start_date' => $supplyDate,
            'end_date' => $supplyDate,
            'status' => $qty > 0 ? 'active' : 'paused',
            'supersedes_doi_id' => null,
            'created_from_action' => 'vendor_seed',
            'closed_by_action' => null,
            'meta' => json_encode([
                'seeded_for' => 'vendor_supply',
                'label' => $label,
                'is_zero_qty_seed' => $qty == 0,
            ]),
            'updated_at' => now(),
        ];

        $this->log('doi payload full', $payload);

        if ($existing) {
            $this->line("  DOI update: date={$supplyDate}, variant_id={$variantId}, qty={$qty}");
            $this->log('doi update full', [
                'id' => $existing->id,
                ...$payload,
            ]);

            if (! $this->dryRun) {
                DB::table('draft_order_items')->where('id', $existing->id)->update($payload);
            }
            return;
        }

        $this->line("  DOI insert: date={$supplyDate}, variant_id={$variantId}, qty={$qty}");
        $this->log('doi insert full', $payload);

        if (! $this->dryRun) {
            DB::table('draft_order_items')->insert([
                ...$payload,
                'created_at' => now(),
            ]);
        }
    }
    private function log(string $message, array $context = []): void
    {
        Log::channel($this->logChannel)->info('[dayli:seed-vendor-milk-supply] ' . $message, $context);
    }
}
