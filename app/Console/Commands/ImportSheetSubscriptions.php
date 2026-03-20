<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlsDate;

use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportSheetSubscriptions extends Command
{


    // command for insert SCR + Draft Orders + Orders + Order Items from sheet with columns: phone=Phone Number, start_date=Start Date, milk=Milk Name (e.g., Vijaya Gold Milk), ask_count=Base Qty (optional, default 1), day_1..day_31=Day-specific qty (optional). The command will group rows by phone number, create one SCR per user, one Draft Order per SCR, and multiple Orders/Order Items based on the day columns. Use --dry-run to preview the mapping without DB writes. Always backup your database before running imports.
    //php artisan import:sheet-subscriptions "C:\Users\mandl\work\flutter projects\orders_users.xlsx" --dry-run --by=1 --default-zone=1 --order-status=draft --month=03 --year=2026
    protected $signature = 'import:sheet-subscriptions
    {xlsx_path : Full path to XLSX}
    {--mode=base : base|sbr|orders|all}
    {--dry-run : Do not write to DB}
    {--by=1 : by_user_id for SCR}
    {--default-zone= : fallback zone_id if users.zone_id is null}
    {--order-status=draft : orders.status (draft|pending|confirmed|fulfilled|cancelled)}
    {--month= : month number for Day columns (1-12)}
    {--year= : year for Day columns (YYYY)}
    {--sheet=0 : sheet index (0-based)}
    {--reuse-existing : Do not create SCR/Draft Order/DOI. Reuse existing only; skip if missing}
    {--today-only : Process day exceptions only till today}
';
    protected $description = 'Import SCR(1 per subscription_type), Draft Orders, Draft Order Items, Orders(order_type=subscription), Order Items from XLSX using users.phone.';

    public function handle(): int
    {
        $path = (string) $this->argument('xlsx_path');
        $mode = strtolower((string) $this->option('mode'));
        $dryRun = (bool) $this->option('dry-run');
        $byUserId = (int) $this->option('by');
        $reuseExisting = (bool) $this->option('reuse-existing');
        $todayOnly = (bool) $this->option('today-only');

        if (!in_array($mode, ['base', 'sbr', 'orders', 'all'], true)) {
            $this->error("Invalid --mode. Use base|sbr|orders|all");
            return self::FAILURE;
        }

        $defaultZone = $this->option('default-zone');
        $defaultZone = ($defaultZone !== null && $defaultZone !== '') ? (int)$defaultZone : null;

        $orderStatus = (string) $this->option('order-status');
        $sheetIndex = (int) $this->option('sheet');

        $optMonth = $this->option('month');
        $optMonth = ($optMonth !== null && $optMonth !== '') ? (int)$optMonth : null;
        $optYear  = $this->option('year');
        $optYear  = ($optYear !== null && $optYear !== '')  ? (int)$optYear  : null;

        if (!is_file($path)) {
            $this->error("XLSX not found: {$path}");
            return self::FAILURE;
        }

        // Cache subscription types by slug/name
        $subTypes = DB::table('subscription_types')->get();
        $subBySlug = [];
        foreach ($subTypes as $st) {
            $slug = strtolower((string)($st->slug ?? ''));
            $name = strtolower((string)($st->name ?? ''));
            if ($slug !== '') $subBySlug[$slug] = (int)$st->id;
            if ($name !== '') $subBySlug[$name] = (int)$st->id;
        }

        try {
            $spreadsheet = IOFactory::load($path);
            $sheet = $spreadsheet->getSheet($sheetIndex);
            $rows = $sheet->toArray(null, true, true, true);

            if (count($rows) < 2) {
                $this->error("No data rows in sheet.");
                return self::FAILURE;
            }

            $headerRow = array_shift($rows);

            // normalize headers by column letter
            $headersByCol = [];
            foreach ($headerRow as $col => $val) {
                $headersByCol[$col] = $this->normKey((string)$val);
            }

            // Required columns
            $colPhone = $this->findCol($headersByCol, 'phone_number');
            $colStart = $this->findCol($headersByCol, 'start_date');

            if (!$colPhone) {
                $this->error("Missing 'Phone Number' column.");
                $this->line("Found headers: " . implode(', ', array_values($headersByCol)));
                return self::FAILURE;
            }

            // In this sheet: "Milk" column is actually the PRODUCT NAME (e.g., Vijaya Gold Milk, Hatsun Curd)
            $colMilkName = $this->findCol($headersByCol, 'milk');
            $colAskCount = $this->findCol($headersByCol, 'ask_count');

            if (!$colMilkName) {
                $this->error("Missing 'Milk' column (product name).");
                $this->line("Found headers: " . implode(', ', array_values($headersByCol)));
                return self::FAILURE;
            }
            if (!$colAskCount) {
                $this->warn("Missing 'Ask Count' column. Will default base_qty=1.");
            }


            // Day columns: day_1..day_31
            $dayCols = []; // day => col
            foreach ($headersByCol as $col => $key) {
                if (preg_match('/^day_(\d{1,2})$/', $key, $m)) {
                    $dayCols[(int)$m[1]] = $col;
                }
            }
            ksort($dayCols);

            $this->info(
                "Detected: phone={$colPhone}"
                    . ($colStart ? ", start_date={$colStart}" : ", start_date=(missing)")
                    . ", milk_name={$colMilkName}" . ($colAskCount ? ", ask_count={$colAskCount}" : ", ask_count=(missing)")

                    . (empty($dayCols) ? ", day_cols=none" : ", day_cols=" . count($dayCols))
            );

            // Counters
            $dupInFile = 0;
            $skipped = 0;

            $createdScr = 0;
            $createdDraftOrders = 0;
            $createdDraftItems = 0;
            $createdOrders = 0;
            $createdOrderItems = 0;

            // Dedupe file by phone (optional)
            // ------------------------------
            // GROUP rows by phone first (merge duplicates instead of skipping)
            // ------------------------------
            $groups = []; // phone => aggregated data
            $rowNum = 1;

            foreach ($rows as $r) {
                $rowNum++;

                $phoneRaw = trim((string)($r[$colPhone] ?? ''));
                $phone = $this->normalizePhone($phoneRaw);
                if ($phone === '') {
                    $skipped++;
                    continue;
                }

                // parse start_date (keep earliest valid)
                $startDate = null;
                if ($colStart) {
                    $startDate = $this->parseDate($r[$colStart] ?? null);
                }

                if (!isset($groups[$phone])) {
                    $groups[$phone] = [
                        'first_row' => $rowNum,
                        'start_date' => $startDate,
                        'items' => [],     // milkName => ['base_qty'=>int, 'start_date'=>string|null, 'days'=>[day=>qty]]

                    ];
                }

                // earliest start_date wins
                if ($startDate && (!$groups[$phone]['start_date'] || $startDate < $groups[$phone]['start_date'])) {
                    $groups[$phone]['start_date'] = $startDate;
                }

                // merge product qty + day qty for each product-type column
                $milkName = trim((string)($r[$colMilkName] ?? ''));
                if ($milkName === '') continue;

                $baseQty = 1;
                if ($colAskCount) {
                    $aq = $this->parseQty((string)($r[$colAskCount] ?? ''));
                    if ($aq !== null && $aq > 0) $baseQty = (int)$aq;
                }

                if (!isset($groups[$phone]['items'][$milkName])) {
                    $groups[$phone]['items'][$milkName] = [
                        'base_qty' => (int)$baseQty,
                        'start_date' => $startDate,
                        'days' => [],
                    ];
                } else {
                    $groups[$phone]['items'][$milkName]['base_qty'] = (int)$baseQty;
                }


                // earliest per-item start_date wins
                if ($startDate && (
                    !$groups[$phone]['items'][$milkName]['start_date'] ||
                    $startDate < $groups[$phone]['items'][$milkName]['start_date']
                )) {
                    $groups[$phone]['items'][$milkName]['start_date'] = $startDate;
                }

                // Merge day columns for this specific milk item
                foreach ($dayCols as $day => $dayCol) {
                    $raw = (string)($r[$dayCol] ?? '');
                    $q = $this->parseQtyAllowZero($raw);

                    if ($q === null) {
                        continue; // truly empty
                    }

                    $existing = $groups[$phone]['items'][$milkName]['days'][$day] ?? null;

                    if ($existing === null) {
                        $groups[$phone]['items'][$milkName]['days'][$day] = (int)$q;
                    } else {
                        $groups[$phone]['items'][$milkName]['days'][$day] = max($existing, (int)$q);
                    }
                }
            }

            // ------------------------------
            // PROCESS each phone group once
            // ------------------------------
            foreach ($groups as $phone => $g) {

                $user = DB::table('users')->where('phone', $phone)->first();
                if (!$user) {
                    $skipped++;
                    $this->warn("Phone {$phone}: user not found (skipping)");
                    continue;
                }

                $zoneId = $user->zone_id ?? $defaultZone;
                $startDate = $g['start_date'];

                if (in_array($mode, ['sbr', 'orders', 'all'], true)) {
                    if ($optYear === null || $optMonth === null) {
                        $this->error("Please pass --month and --year. Example: --month=3 --year=2026");
                        return self::FAILURE;
                    }
                }

                $subTypeId = $this->mapSubscriptionTypeId('milk', $subBySlug);
                if (!$subTypeId) {
                    $skipped++;
                    $this->warn("Phone {$phone}: milk subscription_type_id not found (skipping)");
                    continue;
                }

                try {
                    DB::transaction(function () use (
                        $mode,
                        $dryRun,
                        $todayOnly,
                        $user,
                        $zoneId,
                        $byUserId,
                        $subTypeId,
                        $g,
                        $startDate,
                        $optYear,
                        $optMonth,
                        $orderStatus,
                        $reuseExisting,
                        &$createdScr,
                        &$createdDraftOrders,
                        &$createdDraftItems,
                        &$createdOrders,
                        &$createdOrderItems
                    ) {

                        // -------------------------
                        // BASE: SCR + DR + DOI
                        // -------------------------
                        $base = $this->ensureBaseState(
                            $user,
                            $zoneId,
                            $byUserId,
                            $subTypeId,
                            $g,
                            $startDate,
                            $optYear,
                            $optMonth,
                            $reuseExisting,
                            $dryRun
                        );

                        if (!$dryRun) {
                            $createdScr += $base['created_scr'];
                            $createdDraftOrders += $base['created_draft_orders'];
                            $createdDraftItems += $base['created_draft_items'];
                        }

                        if ($mode === 'base') {
                            return;
                        }

                        // -------------------------
                        // SBR: exceptions from day columns
                        // -------------------------
                        if (in_array($mode, ['sbr', 'all'], true)) {
                            $this->processDayExceptions(
                                $user,
                                $base,
                                $g,
                                (int)$optYear,
                                (int)$optMonth,
                                $todayOnly,
                                $dryRun
                            );
                        }

                        if ($mode === 'sbr') {
                            return;
                        }

                        // -------------------------
                        // ORDERS: create from effective day qtys
                        // -------------------------
                        if (in_array($mode, ['orders', 'all'], true)) {
                            $result = $this->createOrdersFromSheetDays(
                                $user,
                                $zoneId,
                                $base,
                                $g,
                                (int)$optYear,
                                (int)$optMonth,
                                $orderStatus,
                                $todayOnly,
                                $dryRun
                            );

                            if (!$dryRun) {
                                $createdOrders += $result['created_orders'];
                                $createdOrderItems += $result['created_order_items'];
                            }
                        }
                    });
                } catch (Throwable $e) {
                    $skipped++;
                    $this->error("Phone {$phone} FAILED: " . $e->getMessage());
                }
            }

            $this->info("Done.");
            $this->info("Duplicates skipped in file (by phone): {$dupInFile}");
            $this->info("SCR created: {$createdScr}");
            $this->info("Draft Orders created: {$createdDraftOrders}");
            $this->info("Draft Order Items created: {$createdDraftItems}");
            $this->info("Orders created: {$createdOrders}");
            $this->info("Order Items created: {$createdOrderItems}");
            $this->info("Rows skipped/failed: {$skipped}");

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("IMPORT FAILED: " . $e->getMessage());
            return self::FAILURE;
        }
    }
    private function ensureBaseState(
        object $user,
        ?int $zoneId,
        int $byUserId,
        int $subTypeId,
        array $g,
        ?string $startDate,
        ?int $optYear,
        ?int $optMonth,
        bool $reuseExisting,
        bool $dryRun
    ): array {
        $createdScr = 0;
        $createdDraftOrders = 0;
        $createdDraftItems = 0;

        if (!$startDate && $optYear && $optMonth) {
            $startDate = sprintf('%04d-%02d-01', $optYear, $optMonth);
        }
        $groupStart = $startDate ?: now()->toDateString();

        if ($dryRun) {
            return [
                'scr_id' => null,
                'draft_order_id' => null,
                'created_scr' => 0,
                'created_draft_orders' => 0,
                'created_draft_items' => 0,
                'items' => $g['items'] ?? [],
                'group_start' => $groupStart,
                'by_user_id' => $byUserId,
                'zone_id' => $zoneId,
                'subscription_type_id' => $subTypeId,
            ];
        }

        if ($reuseExisting) {
            $existingScr = DB::table('sub_change_requests')
                ->where('for_user_id', (int)$user->id)
                ->where('subscription_type_id', $subTypeId)
                ->orderByDesc('id')
                ->first();

            if (!$existingScr) {
                throw new \RuntimeException("SCR missing in reuse-existing mode");
            }

            $scr = [
                'id' => (int)$existingScr->id,
                'created' => false,
                'needs_draft_link' => empty($existingScr->draft_order_id),
            ];
        } else {
            $scr = $this->ensureScr((int)$user->id, $byUserId, $zoneId, $subTypeId);
            if ($scr['created']) $createdScr++;
        }

        if ($reuseExisting) {
            $existingDraft = DB::table('draft_orders')
                ->where('change_request_id', (int)$scr['id'])
                ->where('status', 'active')
                ->first();

            if (!$existingDraft) {
                throw new \RuntimeException("Draft Order missing in reuse-existing mode");
            }

            $draft = [
                'id' => (int)$existingDraft->id,
                'created' => false,
            ];
        } else {
            $draft = $this->ensureDraftOrder($scr['id'], (int)$user->id, $zoneId, $groupStart);
            if ($draft['created']) $createdDraftOrders++;
        }

        if (($scr['needs_draft_link'] ?? false) && $draft['id']) {
            DB::table('sub_change_requests')->where('id', $scr['id'])->update([
                'draft_order_id' => $draft['id'],
                'updated_at' => now(),
            ]);
        }

        foreach (($g['items'] ?? []) as $milkName => $item) {
            $pv = $this->findProductVariantByMilkName($milkName);
            if (!$pv) {
                continue;
            }

            $itemStart = $item['start_date'] ?? $groupStart;
            $itemStart = $itemStart ?: $groupStart;

            if (!$reuseExisting) {
                $doi = $this->ensureDraftOrderItem(
                    $draft['id'],
                    $pv['product_id'],
                    $pv['variant_id'],
                    (float)max(1, (int)$item['base_qty']),
                    $itemStart,
                    (float)$pv['unit_price']
                );

                if ($doi) $createdDraftItems++;
            }
        }

        return [
            'scr_id' => (int)$scr['id'],
            'draft_order_id' => (int)$draft['id'],
            'created_scr' => $createdScr,
            'created_draft_orders' => $createdDraftOrders,
            'created_draft_items' => $createdDraftItems,
            'items' => $g['items'] ?? [],
            'group_start' => $groupStart,
            'by_user_id' => $byUserId,
            'zone_id' => $zoneId,
            'subscription_type_id' => $subTypeId,
        ];
    }
    private function processDayExceptions(
        object $user,
        array $base,
        array $g,
        int $year,
        int $month,
        bool $todayOnly,
        bool $dryRun
    ): void {
        foreach (($g['items'] ?? []) as $milkName => $item) {
            $askCount = (int)($item['base_qty'] ?? 1);

            foreach (($item['days'] ?? []) as $day => $qty) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, (int)$day);

                if ($todayOnly && $date > now()->toDateString()) {
                    continue;
                }

                if ($qty === null || $qty === '') {
                    continue; // ignore truly empty days
                }

                $qty = (int)$qty;

                if ($qty === $askCount) {
                    continue;
                }

                if ($dryRun) {
                    $this->line("DRY SBR: user={$user->id} milk='{$milkName}' date={$date} ask={$askCount} actual={$qty}");
                    continue;
                }

                $action = ($qty === 0) ? 'pause' : 'modify';

                $this->ensureDailyExceptionScr(
                    baseScrId: (int)$base['scr_id'],
                    forUserId: (int)$user->id,
                    byUserId: (int)$base['by_user_id'],
                    zoneId: $base['zone_id'],
                    subscriptionTypeId: (int)$base['subscription_type_id'],
                    action: $action,
                    date: $date,
                    oldQty: $askCount,
                    newQty: $qty,
                    milkName: $milkName
                );
            }
        }
    }

    private function ensureDailyExceptionScr(
        int $baseScrId,
        int $forUserId,
        int $byUserId,
        ?int $zoneId,
        ?int $subscriptionTypeId,
        string $action, // pause|modify
        string $date,
        int $oldQty,
        int $newQty,
        string $milkName
    ): int {
        $payload = [
            'effective_date' => $date,
            'product_name' => $milkName,
            'old_qty' => $oldQty,
            'new_qty' => $newQty,
        ];

        $meta = [
            'source' => 'sheet-import',
            'import_type' => 'daily-exception',
            'base_scr_id' => $baseScrId,
        ];

        $existing = DB::table('sub_change_requests')
            ->where('from_id', $baseScrId)
            ->where('for_user_id', $forUserId)
            ->where('subscription_type_id', $subscriptionTypeId)
            ->where('action', $action)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.effective_date')) = ?", [$date])
            ->first();

        if ($existing) {
            DB::table('sub_change_requests')->where('id', $existing->id)->update([
                'payload' => json_encode($payload),
                'meta' => json_encode($meta),
                'updated_at' => now(),
            ]);

            return (int)$existing->id;
        }

        return (int) DB::table('sub_change_requests')->insertGetId([
            'for_user_id' => $forUserId,
            'by_user_id' => $byUserId,
            'party_type' => 'consumer',
            'from_id' => $baseScrId,
            'draft_order_id' => null,
            'zone_id' => $zoneId,
            'subscription_type_id' => $subscriptionTypeId,
            'subtypes_json' => json_encode([]),
            'custom_frequency_format' => null,
            'invoice_cycle' => 'monthly',
            'change_reason' => 'staff-error',
            'action' => $action,
            'status' => 'approved',
            'approved_by' => $byUserId,
            'approved_at' => now(),
            'priority' => 3,
            'payload' => json_encode($payload),
            'meta' => json_encode($meta),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
    // -------------------------
    // SCR: 1 per (user, subscription_type_id)
    // -------------------------
    private function ensureScr(int $forUserId, int $byUserId, ?int $zoneId, int $subscriptionTypeId): array
    {
        // If exists, reuse
        $existing = DB::table('sub_change_requests')
            ->where('for_user_id', $forUserId)
            ->where('subscription_type_id', $subscriptionTypeId)
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $needsDraftLink = empty($existing->draft_order_id);
            return ['id' => (int)$existing->id, 'created' => false, 'needs_draft_link' => $needsDraftLink];
        }

        $id = DB::table('sub_change_requests')->insertGetId([
            'for_user_id' => $forUserId,
            'by_user_id' => $byUserId,
            'zone_id' => $zoneId,
            'subscription_type_id' => $subscriptionTypeId,
            'subtypes_json' => json_encode([]),
            'invoice_cycle' => 'monthly',
            'change_reason' => 'staff-error',   // required NOT NULL
            'action' => 'create',
            'status' => 'approved',             // you can change to pending if you want
            'priority' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int)$id, 'created' => true, 'needs_draft_link' => true];
    }

    // -------------------------
    // Draft Orders: 1 active per SCR (unique constraint)
    // -------------------------
    private function ensureDraftOrder(int $changeRequestId, int $customerId, ?int $zoneId, string $startDate): array
    {
        $existing = DB::table('draft_orders')
            ->where('change_request_id', $changeRequestId)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            return ['id' => (int)$existing->id, 'created' => false];
        }

        $id = DB::table('draft_orders')->insertGetId([
            'change_request_id' => $changeRequestId,
            'customer_id' => $customerId,
            'zone_id' => $zoneId,
            'cadence' => 'daily',
            'invoice_cycle' => 'monthly',
            'start_date' => $startDate,
            'status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'title' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int)$id, 'created' => true];
    }

    // -------------------------
    // Draft Order Items: unique by (draft_order_id, variant_id, vendor_id)
    // vendor_id is null -> unique constraint still ok
    // -------------------------
    private function ensureDraftOrderItem(int $draftOrderId, int $productId, int $variantId, float $qty, string $startDate, float $unitPrice): bool
    {
        $exists = DB::table('draft_order_items')
            ->where('draft_order_id', $draftOrderId)
            ->where('variant_id', $variantId)
            ->whereNull('vendor_id')
            ->exists();

        if ($exists) return false;

        DB::table('draft_order_items')->insert([
            'draft_order_id' => $draftOrderId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'vendor_id' => null,
            'frequency_type' => 'daily',
            'qty' => $qty,
            'unit' => 'pcs',
            'price_snapshot' => $unitPrice,
            'start_date' => $startDate,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    // -------------------------
    // Orders: order_type=subscription, one per (customer_id, date, draft_order_id)
    // -------------------------
    private function ensureOrder(int $customerId, ?int $zoneId, int $draftOrderId, string $status, string $date): array
    {
        // Use orders.number unique — we will set it to a stable value
        $number = "sub:{$draftOrderId}:{$date}";

        $existing = DB::table('orders')->where('number', $number)->first();
        if ($existing) {
            return ['id' => (int)$existing->id, 'created' => false];
        }

        $id = DB::table('orders')->insertGetId([
            'order_type' => 'subscription',
            'customer_id' => $customerId,
            'zone_id' => $zoneId,
            'draft_order_id' => $draftOrderId,
            'number' => $number,
            'status' => $status,
            'confirmed' => ($status === 'confirmed' || $status === 'fulfilled') ? 1 : 0,
            'closed' => ($status === 'fulfilled' || $status === 'cancelled') ? 1 : 0,
            'requires_shipping' => 1,
            'taxes_included' => 0,
            'tax_exempt' => 0,
            'test' => 0,
            'unpaid' => 0,
            'subtotal' => 0,
            'tax' => 0,
            'discount' => 0,
            'total' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => (int)$id, 'created' => true];
    }

    // -------------------------
    // Order Items: one per order+variant
    // title is required NOT NULL
    // -------------------------
    function ensureOrderItem(
        int $orderId,
        int $productId,
        int $variantId,
        string $title,
        int $qty,
        float $unitPrice,
        string $actualsDate // ✅ YYYY-MM-DD
    ): bool {
        $existing = DB::table('order_items')
            ->where('order_id', $orderId)
            ->where('variant_id', $variantId)
            ->first();

        $lineTotal = round($unitPrice * $qty, 2);

        if ($existing) {
            DB::table('order_items')->where('id', $existing->id)->update([
                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'actuals_date' => $actualsDate, // ✅ add this
                'updated_at' => now(),
            ]);
            return false;
        }

        DB::table('order_items')->insert([
            'order_id' => $orderId,
            'product_id' => $productId,
            'variant_id' => $variantId,
            'title' => $title,
            'quantity' => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
            'actuals_date' => $actualsDate, // ✅ add this
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    // -------------------------
    // Product/Variant selection by product_sub_type
    // -------------------------
    private function findProductVariantByMilkName(string $milkName): ?array
    {
        $t = $this->normalizeMilkName($milkName);
        if ($t === '') return null;

        // try product title match first
        $p = DB::table('products')
            ->select('product_id', 'title')
            ->whereRaw('LOWER(title) LIKE ?', ['%' . $t . '%'])
            ->orderByRaw('LENGTH(title) ASC')
            ->first();

        if ($p) {
            // pick first variant
            $v = DB::table('variants')
                ->select('variant_id', 'title', 'price', 'position')
                ->where('product_id', $p->product_id)
                ->orderBy('position')
                ->first();

            if (!$v) return null;

            return [
                'product_id' => (int)$p->product_id,
                'variant_id' => (int)$v->variant_id,
                'title' => (string)$p->title,
                'unit_price' => (float)($v->price ?? 0),
            ];
        }

        // fallback: variant title match
        $pv = DB::table('variants')
            ->join('products', 'products.product_id', '=', 'variants.product_id')
            ->select(
                'products.product_id',
                'products.title as product_title',
                'variants.variant_id',
                'variants.title as variant_title',
                'variants.price'
            )
            ->whereRaw('LOWER(variants.title) LIKE ?', ['%' . $t . '%'])
            ->orderByRaw('LENGTH(variants.title) ASC')
            ->first();

        if (!$pv) return null;

        return [
            'product_id' => (int)$pv->product_id,
            'variant_id' => (int)$pv->variant_id,
            'title' => (string)$pv->product_title,
            'unit_price' => (float)($pv->price ?? 0),
        ];
    }

    private function normalizeMilkName(string $s): string
    {
        $t = mb_strtolower(trim($s));
        $t = str_replace(['_', '-', '(', ')'], ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t);

        // remove ml text for matching but keep meaning
        $t = preg_replace('/\b\d+\s*ml\b/', '', $t);
        $t = preg_replace('/\s+/', ' ', $t);

        return trim($t);
    }


    // -------------------------
    // Map sheet key -> subscription_type_id (milk, vegetables, etc)
    // -------------------------
    private function mapSubscriptionTypeId(string $ptypeKey, array $subBySlug): ?int
    {
        $k = strtolower(trim($ptypeKey));

        // ✅ HARD RULE: milk always maps to MILK subscription
        if ($k === 'milk') {
            foreach ($subBySlug as $key => $id) {
                if (str_contains($key, 'milk')) {
                    return (int)$id;
                }
            }
            return null;
        }

        // common alias
        if ($k === 'veg' || $k === 'vegetable') $k = 'vegetables';

        // direct match
        if (isset($subBySlug[$k])) return (int)$subBySlug[$k];

        // fallback: contains
        foreach ($subBySlug as $key => $id) {
            if (str_contains($key, $k)) return (int)$id;
        }

        return null;
    }


    // -------------------------
    // Build dates list:
    // - If day cols have numbers -> use those as qty
    // - Else -> single entry on start_date with base qty
    // -------------------------
    private function buildDateQtyList(array $daysMap, int $year, int $month, string $startDate, int $baseQty): array
    {
        $out = [];

        // daysMap is [dayNumber => qty]
        if (!empty($daysMap)) {
            foreach ($daysMap as $day => $q) {
                $q = (int)$q;
                if ($q <= 0) continue;

                $out[] = [sprintf('%04d-%02d-%02d', $year, $month, (int)$day), $q];
            }
        }

        // ✅ OPTION C:
        // If there are no day values in sheet, DO NOT create any orders.
        return $out;
    }


    // -------------------------
    // Utils
    // -------------------------
    private function normKey(string $h): string
    {
        $h = trim($h);
        $h = str_replace(["\r", "\n", "\t"], ' ', $h);
        $h = preg_replace('/\s+/', ' ', $h);
        $h = trim($h, "\"' ");
        $h = strtolower($h);
        $h = preg_replace('/[^a-z0-9]+/', '_', $h);
        return trim($h, '_');
    }

    private function findCol(array $headersByCol, string $wantKey): ?string
    {
        foreach ($headersByCol as $col => $key) {
            if ($key === $wantKey) return $col;
        }
        return null;
    }

    private function normalizePhone(string $phone): string
    {
        $p = trim($phone);

        // handle Excel scientific notation
        if ($p !== '' && preg_match('/e\+?/i', $p)) {
            $p = sprintf('%.0f', (float) $p);
        }

        $digits = preg_replace('/\D+/', '', $p);
        if (strlen($digits) > 10) $digits = substr($digits, -10);

        return (strlen($digits) === 10) ? $digits : '';
    }

    private function parseQty(string $v): ?int
    {
        $v = trim($v);
        if ($v === '') return null;

        $clean = preg_replace('/[^0-9.]+/', '', $v);
        if ($clean === '') return null;

        $n = (float)$clean;
        if ($n <= 0) return null;

        return (int) round($n);
    }

    private function parseQtyAllowZero(string $v): ?int
    {
        $v = trim($v);
        if ($v === '') return null;

        $clean = preg_replace('/[^0-9.]+/', '', $v);
        if ($clean === '') return null;

        $n = (float)$clean;
        if ($n < 0) return null;

        return (int) round($n);
    }
    private function createOrdersFromSheetDays(
        object $user,
        ?int $zoneId,
        array $base,
        array $g,
        int $year,
        int $month,
        string $orderStatus,
        bool $todayOnly,
        bool $dryRun
    ): array {
        $createdOrders = 0;
        $createdOrderItems = 0;

        $ordersByDate = [];

        foreach (($g['items'] ?? []) as $milkName => $item) {
            $pv = $this->findProductVariantByMilkName($milkName);
            if (!$pv) {
                continue;
            }

            foreach (($item['days'] ?? []) as $day => $qty) {
                $date = sprintf('%04d-%02d-%02d', $year, $month, (int)$day);

                if ($todayOnly && $date > now()->toDateString()) {
                    continue;
                }

                if ($qty === null || $qty === '') {
                    continue; // ignore truly empty days
                }

                $qty = (int)$qty;
                if ($qty <= 0) {
                    continue;
                }
                $ordersByDate[$date][] = [
                    'product_id' => $pv['product_id'],
                    'variant_id' => $pv['variant_id'],
                    'title' => $pv['title'],
                    'unit_price' => (float)$pv['unit_price'],
                    'qty' => $qty,
                ];
            }
        }

        if ($dryRun) {
            return [
                'created_orders' => 0,
                'created_order_items' => 0,
            ];
        }

        foreach ($ordersByDate as $date => $items) {
            $orderId = $this->ensureOrder(
                (int)$user->id,
                $zoneId,
                (int)$base['draft_order_id'],
                $orderStatus,
                $date
            );

            if ($orderId['created']) $createdOrders++;

            foreach ($items as $it) {
                $oi = $this->ensureOrderItem(
                    $orderId['id'],
                    (int)$it['product_id'],
                    (int)$it['variant_id'],
                    (string)$it['title'],
                    (int)$it['qty'],
                    (float)$it['unit_price'],
                    $date
                );

                if ($oi) $createdOrderItems++;
            }
        }

        return [
            'created_orders' => $createdOrders,
            'created_order_items' => $createdOrderItems,
        ];
    }

    private function parseDate($v): ?string
    {
        // DateTime from PhpSpreadsheet
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }

        if ($v === null) return null;

        // Excel date serial number
        if (is_numeric($v)) {
            try {
                $dt = XlsDate::excelToDateTimeObject((float)$v);
                return $dt->format('Y-m-d');
            } catch (\Throwable $e) {
                // fallthrough
            }
        }

        $s = trim((string)$v);
        if ($s === '') return null;

        // Already ISO
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

        // dd/mm/yyyy or dd-mm-yyyy
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        // ✅ dd/mm/yy or dd-mm-yy  (THIS fixes 24/02/24 etc)
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2})$/', $s, $m)) {
            $yy = (int)$m[3];
            // interpret 00-69 => 2000-2069, 70-99 => 1970-1999 (like many systems)
            $year = ($yy <= 69) ? (2000 + $yy) : (1900 + $yy);
            return sprintf('%04d-%02d-%02d', $year, (int)$m[2], (int)$m[1]);
        }

        return null;
    }



    private function ymFromDate(string $ymd): array
    {
        // yyyy-mm-dd
        $y = (int)substr($ymd, 0, 4);
        $m = (int)substr($ymd, 5, 2);
        return [$y, $m];
    }
}
