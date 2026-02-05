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
    protected $signature = 'import:sheet-subscriptions
        {xlsx_path : Full path to XLSX}
        {--dry-run : Do not write to DB}
        {--by=1 : by_user_id for SCR}
        {--default-zone= : fallback zone_id if users.zone_id is null}
        {--order-status=draft : orders.status (draft|pending|confirmed|fulfilled|cancelled)}
        {--month= : month number for Day columns (1-12). If empty, uses start_date month}
        {--year= : year for Day columns (YYYY). If empty, uses start_date year}
        {--sheet=0 : sheet index (0-based)}
        {--reuse-existing : Do not create SCR/Draft Order/DOI. Reuse existing only; skip if missing}

    ';

    protected $description = 'Import SCR(1 per subscription_type), Draft Orders, Draft Order Items, Orders(order_type=subscription), Order Items from XLSX using users.phone.';

    public function handle(): int
    {
        $path = (string) $this->argument('xlsx_path');
        $dryRun = (bool) $this->option('dry-run');
        $byUserId = (int) $this->option('by');
        $reuseExisting = (bool) $this->option('reuse-existing');

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
                        'base_qty' => 0,
                        'start_date' => $startDate, // per-item start date
                        'days' => [],
                    ];
                }

                $groups[$phone]['items'][$milkName]['base_qty'] += $baseQty;

                // earliest per-item start_date wins
                if ($startDate && (
                    !$groups[$phone]['items'][$milkName]['start_date'] ||
                    $startDate < $groups[$phone]['items'][$milkName]['start_date']
                )) {
                    $groups[$phone]['items'][$milkName]['start_date'] = $startDate;
                }

                // Merge day columns for this specific milk item
                foreach ($dayCols as $day => $dayCol) {
                    $q = $this->parseQty((string)($r[$dayCol] ?? ''));
                    if ($q === null || $q <= 0) continue;

                    $groups[$phone]['items'][$milkName]['days'][$day] =
                        ($groups[$phone]['items'][$milkName]['days'][$day] ?? 0) + (int)$q;
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

                $startDate = $g['start_date']; // may be null (but you said sheet has it)

                // Month/year from start_date OR CLI override
                // Month/year MUST come from CLI for Day1..Day31 (billing month)
                if ($optYear === null || $optMonth === null) {
                    $this->error("Please pass --month and --year for Day columns. Example: --month=12 --year=2025");
                    return self::FAILURE;
                }

                $year = $optYear;
                $month = $optMonth;

                // For each product type in this phone group
                $subTypeId = $this->mapSubscriptionTypeId('milk', $subBySlug);
                if (!$subTypeId) {
                    $skipped++;
                    $this->warn("Phone {$phone}: milk subscription_type_id not found (skipping)");
                    continue;
                }

                // Build all order items by date: date => list of items
                $ordersByDate = []; // 'YYYY-MM-DD' => [ ['product_id'=>..,'variant_id'=>..,'title'=>..,'unit_price'=>..,'qty'=>..], ... ]

                try {
                    if ($dryRun) {
                        foreach (($g['items'] ?? []) as $milkName => $item) {
                            $pv = $this->findProductVariantByMilkName($milkName);
                            if (!$pv) {
                                $this->warn("DRY: user={$user->id} phone={$phone} milk='{$milkName}' => product/variant NOT FOUND");
                                continue;
                            }

                            $itemStart = $item['start_date'] ?? $startDate;

                            // ✅ if missing, fallback to import month start (ex: 2025-11-01)
                            if (!$itemStart && $optYear && $optMonth) {
                                $itemStart = sprintf('%04d-%02d-01', (int)$optYear, (int)$optMonth);
                            }

                            $itemStart = $itemStart ?: now()->toDateString();

                            // month/year from itemStart (better than global)
                            [$yS, $mS] = $this->ymFromDate($itemStart);
                            $yearUse = $optYear ?? $yS;
                            $monthUse = $optMonth ?? $mS;

                            $dateQty = $this->buildDateQtyList($item['days'] ?? [], $yearUse, $monthUse, $itemStart, (int)$item['base_qty']);

                            $this->line("DRY: user={$user->id} phone={$phone} milk='{$milkName}' product={$pv['product_id']} variant={$pv['variant_id']} start={$itemStart} orders=" . count($dateQty));
                        }
                        // no DB writes in dry-run
                        continue;
                    }

                    DB::transaction(function () use (
                        $user,
                        $zoneId,
                        $byUserId,
                        $subTypeId,
                        $g,
                        $startDate,
                        $optYear,
                        $optMonth,
                        $orderStatus,
                        &$createdScr,
                        &$createdDraftOrders,
                        &$createdDraftItems,
                        &$createdOrders,
                        &$createdOrderItems,
                        $reuseExisting,

                    ) {
                        // 1) SCR once per (user + milk subscription_type_id)

                        if ($reuseExisting) {
                            // ✅ reuse existing only (do not create)
                            $existingScr = DB::table('sub_change_requests')
                                ->where('for_user_id', (int)$user->id)
                                ->where('subscription_type_id', $subTypeId)
                                ->orderByDesc('id')
                                ->first();

                            if (!$existingScr) {
                                // skip this user completely if SCR missing
                                return;
                            }

                            $scrId = [
                                'id' => (int)$existingScr->id,
                                'created' => false,
                                'needs_draft_link' => empty($existingScr->draft_order_id),
                            ];
                        } else {
                            // OLD (keep)
                            // $scrId = $this->ensureScr((int)$user->id, $byUserId, $zoneId, $subTypeId);
                            // if ($scrId['created']) $createdScr++;

                            $scrId = $this->ensureScr((int)$user->id, $byUserId, $zoneId, $subTypeId);
                            if ($scrId['created']) $createdScr++;
                        }

                        // 2) Draft Order once per SCR
                        $groupStart = $startDate;

                        // ✅ fallback to import month start (NOT today)
                        if (!$groupStart && $optYear && $optMonth) {
                            $groupStart = sprintf('%04d-%02d-01', (int)$optYear, (int)$optMonth);
                        }
                        $groupStart = $groupStart ?: now()->toDateString();

                        if ($reuseExisting) {
                            // ✅ reuse existing only
                            $existingDraft = DB::table('draft_orders')
                                ->where('change_request_id', (int)$scrId['id'])
                                ->where('status', 'active')
                                ->first();

                            if (!$existingDraft) {
                                // skip this user completely if Draft Order missing
                                return;
                            }

                            $draftOrderId = ['id' => (int)$existingDraft->id, 'created' => false];
                        } else {
                            // OLD (keep)
                            // $draftOrderId = $this->ensureDraftOrder($scrId['id'], (int)$user->id, $zoneId, $groupStart);
                            // if ($draftOrderId['created']) $createdDraftOrders++;

                            $draftOrderId = $this->ensureDraftOrder($scrId['id'], (int)$user->id, $zoneId, $groupStart);
                            if ($draftOrderId['created']) $createdDraftOrders++;
                        }


                        // link scr.draft_order_id if needed
                        if ($scrId['needs_draft_link'] && $draftOrderId['id']) {
                            DB::table('sub_change_requests')->where('id', $scrId['id'])->update([
                                'draft_order_id' => $draftOrderId['id'],
                                'updated_at' => now(),
                            ]);
                        }

                        // 3) For each milk item row => create DOI + accumulate daily order_items
                        $ordersByDate = [];

                        foreach (($g['items'] ?? []) as $milkName => $item) {

                            $pv = $this->findProductVariantByMilkName($milkName);
                            if (!$pv) {
                                // skip item if not found
                                continue;
                            }

                            $itemStart = $item['start_date'] ?? $groupStart;
                            $itemStart = $itemStart ?: $groupStart;

                            if (!$reuseExisting) {
                                // OLD (keep)
                                // $doi = $this->ensureDraftOrderItem(...);
                                // if ($doi) $createdDraftItems++;

                                $doi = $this->ensureDraftOrderItem(
                                    $draftOrderId['id'],
                                    $pv['product_id'],
                                    $pv['variant_id'],
                                    (float)max(1, (int)$item['base_qty']),
                                    $itemStart,
                                    (float)$pv['unit_price']
                                );
                                if ($doi) $createdDraftItems++;
                            } else {
                                // ✅ reuse mode: do not create DOI
                                // (orders/order_items will still be created below and linked to same draft_order_id)
                            }


                            // Month/year from itemStart
                            [$yS, $mS] = $this->ymFromDate($itemStart);
                            $yearUse = $optYear ?? $yS;
                            $monthUse = $optMonth ?? $mS;

                            // Build dates from this item's own day qtys
                            $dateQty = $this->buildDateQtyList($item['days'] ?? [], $yearUse, $monthUse, $itemStart, (int)$item['base_qty']);

                            foreach ($dateQty as [$date, $qty]) {
                                $qty = (int)$qty;
                                if ($qty <= 0) continue;

                                $ordersByDate[$date][] = [
                                    'product_id' => $pv['product_id'],
                                    'variant_id' => $pv['variant_id'],
                                    'title' => $pv['title'],
                                    'unit_price' => (float)$pv['unit_price'],
                                    'qty' => $qty,
                                ];
                            }
                        }

                        // 4) Create one order per date, and multiple order_items inside it
                        foreach ($ordersByDate as $date => $items) {
                            $orderId = $this->ensureOrder((int)$user->id, $zoneId, $draftOrderId['id'], $orderStatus, $date);
                            if ($orderId['created']) $createdOrders++;

                            foreach ($items as $it) {
                                $oi = $this->ensureOrderItem(
                                    $orderId['id'],
                                    (int)$it['product_id'],
                                    (int)$it['variant_id'],
                                    (string)$it['title'],
                                    (int)$it['qty'],
                                    (float)$it['unit_price'],
                                    $date // ✅ actuals_date
                                );
                                if ($oi) $createdOrderItems++;
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
