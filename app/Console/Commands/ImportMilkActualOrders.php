<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMilkActualOrders extends Command
{
    protected $signature = 'milk:import-actual-orders
        {july_file : July workbook path}
        {august_file : August workbook path}
        {--from=2026-07-01 : First delivery date to import}
        {--to=2026-08-05 : Last delivery date to import}
        {--zone_id=1 : Zone ID}
        {--dry-run : Validate and preview without writing}
        {--replace : Delete existing matching-range subscription orders before importing}';

    protected $description =
    'Import actual July/August milk deliveries into orders and order_items from the operational workbooks';

    private const SOURCE = 'milk_actual_sheet_reimport_2026_07_01_08_05';

    /**
     * Sheet key aliases. Values are searched against products.title and
     * variants.title after punctuation/spacing normalization.
     */
    private const PRODUCT_ALIASES = [
        'vijayagold' => ['vijayagoldmilk500ml'],
        'vijayatm' => ['vijayatonedmilk500ml'],
        'vijayatmsmall' => ['vijayatonedmilksmall'],
        'vijayacurd' => ['vijayacurd500ml'],
        'vijayacurdsmall' => ['vijayacurdsmall'],
        'vijayabuttermilksmall' => ['vijayabuttermilk', 'vijayabuttermilksmall'],
        'arokyagold' => ['arokyagold500ml'],
        'arokyatm' => ['arokyatonedmilk500ml'],
        'arokyatmsmall' => ['arokyatmsmall'],
        'hatsuncurd' => ['hatsuncurdbig400g'],
        'hatsuncurdsmall' => ['hatsuncurdsmall110g'],
        'sangamgold' => ['sangammilk500ml', 'sangamgold'],
    ];

    public function handle(): int
    {
        $julyFile = (string) $this->argument('july_file');
        $augustFile = (string) $this->argument('august_file');
        $from = Carbon::parse((string) $this->option('from'))->startOfDay();
        $to = Carbon::parse((string) $this->option('to'))->startOfDay();
        $zoneId = (int) $this->option('zone_id');

        foreach ([$julyFile, $augustFile] as $file) {
            if (!is_file($file)) {
                $this->error("Workbook not found: {$file}");
                return self::FAILURE;
            }
        }

        if ($from->gt($to)) {
            $this->error('--from must be before or equal to --to.');
            return self::FAILURE;
        }

        $this->info('Reading actual-delivery workbooks...');

        $rows = array_merge(
            $this->readWorkbook($julyFile, 2026, 7, $from, $to),
            $this->readWorkbook($augustFile, 2026, 8, $from, $to),
        );

        if ($rows === []) {
            $this->warn('No positive delivery quantities were found in the requested date range.');
            return self::SUCCESS;
        }

        $stats = [
            'sheet_rows' => count($rows),
            'resolved_rows' => 0,
            'orders_created' => 0,
            'items_created' => 0,
            'customers_missing' => 0,
            'customers_ambiguous' => 0,
            'products_missing' => 0,
            'draft_orders_missing' => 0,
            'duplicate_sheet_keys' => 0,
            'amount' => 0.0,
        ];

        $resolved = [];
        $seen = [];

        foreach ($rows as $row) {
            /*
     * Newspaper is outside this milk-order import.
     * Ignore it completely and do not count it as a mapping error.
     */
            if ($this->normalizeKey((string) $row['product_key']) === 'eenadunewspaper') {
                continue;
            }

            $customer = $this->resolveCustomer($row['customer_name'], $row['phone']);
            if ($customer['status'] === 'missing') {
                $stats['customers_missing']++;
                $this->warn("Customer missing | {$row['customer_name']} | {$row['phone']} | {$row['date']}");
                continue;
            }

            if ($customer['status'] === 'ambiguous') {
                $stats['customers_ambiguous']++;
                $this->warn("Customer ambiguous | {$row['customer_name']} | {$row['phone']} | {$row['date']}");
                continue;
            }

            $customerId = (int) $customer['user']->id;
            $mapping = $this->resolveProductMapping(
                $customerId,
                $zoneId,
                $row['product_key'],
                $row['date']
            );

            if (!$mapping) {
                $stats['products_missing']++;
                $this->warn(
                    "Product mapping missing | user={$customerId} | {$row['customer_name']} | " .
                        "{$row['product_key']} | {$row['date']}"
                );
                continue;
            }

            $dedupeKey = implode('|', [
                $customerId,
                $row['date'],
                $mapping->product_id,
                $mapping->variant_id,
            ]);

            if (isset($seen[$dedupeKey])) {
                // Multiple sheet lines for the same customer/date/product are added together.
                $stats['duplicate_sheet_keys']++;
                $resolved[$seen[$dedupeKey]]['quantity'] += $row['quantity'];
                $resolved[$seen[$dedupeKey]]['line_total'] = round(
                    $resolved[$seen[$dedupeKey]]['quantity'] *
                        $resolved[$seen[$dedupeKey]]['unit_price'],
                    2
                );
                continue;
            }

            $unitPrice = round((float) $mapping->current_variant_price, 2);
            $lineTotal = round($row['quantity'] * $unitPrice, 2);

            $resolved[] = [
                'customer_id' => $customerId,
                'customer_name' => trim((string) ($customer['user']->name ?? $row['customer_name'])),
                'phone' => $this->normalizePhone((string) ($customer['user']->phone ?? $row['phone'])),
                'date' => $row['date'],
                'draft_order_id' => $mapping->draft_order_id !== null ? (int) $mapping->draft_order_id : null,
                'vendor_id' => $mapping->vendor_id !== null ? (int) $mapping->vendor_id : null,
                'zone_id' => (int) ($mapping->zone_id ?? $zoneId),
                'product_id' => (int) $mapping->product_id,
                'variant_id' => (int) $mapping->variant_id,
                'product_title' => (string) $mapping->product_title,
                'variant_title' => (string) $mapping->variant_title,
                'quantity' => (float) $row['quantity'],
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
                'sheet_product_key' => $row['product_key'],
                'sheet_name' => $row['sheet_name'],
            ];

            $seen[$dedupeKey] = array_key_last($resolved);
            $stats['resolved_rows']++;
        }

        $this->showValidationSummary($stats, $resolved);

        $blockingErrors =
            $stats['customers_missing'] +
            $stats['customers_ambiguous'] +
            $stats['products_missing'];

        if ($blockingErrors > 0) {
            $this->error('Import stopped: resolve all missing/ambiguous mappings first. Nothing was written.');
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->info('DRY RUN SUCCESS — no database changes were made.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($resolved, $from, $to, $zoneId, &$stats): void {
            if ($this->option('replace')) {
                DB::table('orders')
                    ->whereBetween('delivery_date', [$from->toDateString(), $to->toDateString()])
                    ->where('zone_id', $zoneId)
                    ->where('order_type', 'subscription')
                    ->delete();
            } else {
                $existing = DB::table('orders')
                    ->whereBetween('delivery_date', [$from->toDateString(), $to->toDateString()])
                    ->where('zone_id', $zoneId)
                    ->where('order_type', 'subscription')
                    ->exists();

                if ($existing) {
                    throw new \RuntimeException(
                        'Subscription orders already exist in this date range. ' .
                            'Use --replace only after confirming they may be deleted.'
                    );
                }
            }

            $grouped = collect($resolved)
                ->groupBy(fn(array $r) => $r['customer_id'] . '|' . $r['date']);

            foreach ($grouped as $group) {
                $items = $group->values()->all();
                $first = $items[0];
                $subtotal = round(array_sum(array_column($items, 'line_total')), 2);
                $itemCount = (int) round(array_sum(array_column($items, 'quantity')));
                $now = now();

                $orderId = DB::table('orders')->insertGetId([
                    'order_type' => 'subscription',
                    'customer_id' => $first['customer_id'],
                    'vendor_id' => $first['vendor_id'],
                    'zone_id' => $first['zone_id'],
                    'delivery_date' => $first['date'],
                    'delivery_status' => 'delivered',
                    'delivered_at' => Carbon::parse($first['date'])->endOfDay(),
                    'draft_order_id' => $first['draft_order_id'],
                    'status' => 'fulfilled',
                    'confirmed' => 1,
                    'closed' => 1,
                    'requires_shipping' => 1,
                    'unpaid' => 1,
                    'current_total' => $subtotal,
                    'current_subtotal' => $subtotal,
                    'subtotal' => $subtotal,
                    'total' => $subtotal,
                    'item_count' => $itemCount,
                    'line_items_subtotal_price' => $subtotal,
                    'total_net_amount' => $subtotal,
                    'currency' => 'INR',
                    'currency_code' => 'INR',
                    'phone' => $first['phone'] ?: null,
                    'source_name' => self::SOURCE,
                    'meta' => json_encode([
                        'import_source' => self::SOURCE,
                        'actual_sheet_import' => true,
                        'customer_name' => $first['customer_name'],
                        'workbook_date_range' => [
                            'from' => $from->toDateString(),
                            'to' => $to->toDateString(),
                        ],
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($items as $item) {
                    DB::table('order_items')->insert([
                        'order_id' => $orderId,
                        'product_id' => $item['product_id'],
                        'variant_id' => $item['variant_id'],
                        'title' => $item['product_title'],
                        'variant' => $item['variant_title'],
                        'quantity' => (int) round($item['quantity']),
                        'unit_price' => $item['unit_price'],
                        'line_total' => $item['line_total'],
                        'actuals_date' => $item['date'],
                        'meta' => json_encode([
                            'import_source' => self::SOURCE,
                            'actual_sheet_import' => true,
                            'sheet_product_key' => $item['sheet_product_key'],
                            'sheet_name' => $item['sheet_name'],
                            'price_source' => 'variants.price',
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $stats['items_created']++;
                    $stats['amount'] += $item['line_total'];
                }

                $stats['orders_created']++;
            }
        });

        $this->newLine();
        $this->info('Actual milk orders imported successfully.');
        $this->table(
            ['Result', 'Count / Amount'],
            [
                ['Orders created', $stats['orders_created']],
                ['Order items created', $stats['items_created']],
                ['Imported amount', '₹' . number_format($stats['amount'], 2)],
            ]
        );

        return self::SUCCESS;
    }

    private function readWorkbook(
        string $file,
        int $year,
        int $month,
        Carbon $from,
        Carbon $to
    ): array {
        $spreadsheet = IOFactory::load($file);
        $result = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $matrix = $sheet->toArray(null, true, true, false);

            /*
             * Operational workbook layout:
             * - Day headers are on one row (for example Day 31 ... Day 1)
             * - Name / Phone No. / Milk are on the next row
             * - Customer delivery rows start immediately below it
             */
            $customerHeaderRow = $this->findCustomerHeaderRow($matrix);

            if ($customerHeaderRow === null) {
                $this->warn("Customer header not found | sheet={$sheet->getTitle()}");
                continue;
            }

            $dayHeaderRow = $customerHeaderRow - 1;
            if ($dayHeaderRow < 0 || !isset($matrix[$dayHeaderRow])) {
                $this->warn("Day header not found | sheet={$sheet->getTitle()}");
                continue;
            }

            $customerHeader = $matrix[$customerHeaderRow];
            $dayHeader = $matrix[$dayHeaderRow];

            $nameCol = $this->findHeaderColumn($customerHeader, ['name']);
            $phoneCol = $this->findHeaderColumn(
                $customerHeader,
                ['phoneno', 'phonenumber', 'phone']
            );
            $milkCol = $this->findHeaderColumn(
                $customerHeader,
                ['milk', 'milktype']
            );

            $dayColumns = [];
            foreach ($dayHeader as $col => $value) {
                $normalized = $this->normalizeKey((string) $value);

                if (preg_match('/^day(\d{1,2})$/', $normalized, $m)) {
                    $dayColumns[$col] = (int) $m[1];
                }
            }

            if ($nameCol === null || $milkCol === null || $dayColumns === []) {
                $this->warn(
                    "Required columns missing | sheet={$sheet->getTitle()} | " .
                        'name=' . ($nameCol ?? 'NULL') .
                        ' milk=' . ($milkCol ?? 'NULL') .
                        ' days=' . count($dayColumns)
                );
                continue;
            }

            for (
                $r = $customerHeaderRow + 1, $max = count($matrix);
                $r < $max;
                $r++
            ) {
                $row = $matrix[$r];
                $name = trim((string) ($row[$nameCol] ?? ''));
                $productKey = trim((string) ($row[$milkCol] ?? ''));
                $phone = $phoneCol !== null
                    ? $this->normalizePhone((string) ($row[$phoneCol] ?? ''))
                    : '';

                if ($name === '' || $productKey === '') {
                    continue;
                }

                foreach ($dayColumns as $col => $day) {
                    if (!checkdate($month, $day, $year)) {
                        continue;
                    }

                    $date = Carbon::create($year, $month, $day)->startOfDay();

                    if ($date->lt($from) || $date->gt($to)) {
                        continue;
                    }

                    $qty = $this->toNumber($row[$col] ?? null);

                    if ($qty <= 0) {
                        continue;
                    }

                    $result[] = [
                        'customer_name' => $name,
                        'phone' => $phone,
                        'product_key' => $productKey,
                        'date' => $date->toDateString(),
                        'quantity' => $qty,
                        'sheet_name' => $sheet->getTitle(),
                    ];
                }
            }
        }

        return $result;
    }

    private function findCustomerHeaderRow(array $matrix): ?int
    {
        foreach ($matrix as $index => $row) {
            $normalized = array_map(
                fn($value) => $this->normalizeKey((string) $value),
                $row
            );

            $hasName = in_array('name', $normalized, true);
            $hasMilk = in_array('milk', $normalized, true)
                || in_array('milktype', $normalized, true);
            $hasPhone = in_array('phoneno', $normalized, true)
                || in_array('phonenumber', $normalized, true)
                || in_array('phone', $normalized, true);

            if ($hasName && $hasMilk && $hasPhone) {
                return $index;
            }
        }

        return null;
    }

    private function findHeaderColumn(array $header, array $keys): ?int
    {
        foreach ($header as $col => $value) {
            $key = $this->normalizeKey((string) $value);
            if (in_array($key, $keys, true)) {
                return $col;
            }
        }

        return null;
    }

    private function resolveCustomer(string $sheetName, string $sheetPhone): array
    {
        $phone = $this->normalizePhone($sheetPhone);

        if ($phone !== '') {
            $byPhone = DB::table('users')
                ->whereRaw(
                    "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''), '+', ''), ' ', ''), '-', ''), '(', ''), ')', ''), 10) = ?",
                    [substr($phone, -10)]
                )
                ->get();

            if ($byPhone->count() === 1) {
                return ['status' => 'ok', 'user' => $byPhone->first()];
            }

            if ($byPhone->count() > 1) {
                return ['status' => 'ambiguous'];
            }
        }

        $target = $this->normalizeKey($sheetName);
        $matches = DB::table('users')
            ->select('id', 'name', 'phone')
            ->get()
            ->filter(fn($u) => $this->normalizeKey((string) $u->name) === $target)
            ->values();

        if ($matches->count() === 1) {
            return ['status' => 'ok', 'user' => $matches->first()];
        }

        return ['status' => $matches->isEmpty() ? 'missing' : 'ambiguous'];
    }

    private function resolveProductMapping(
        int $customerId,
        int $zoneId,
        string $sheetProductKey,
        string $date
    ): ?object {
        $sheetKey = $this->normalizeKey($sheetProductKey);
        $aliases = self::PRODUCT_ALIASES[$sheetKey] ?? [$sheetKey];

        /*
         * 1) Best match: customer's DOI valid on the delivery date.
         */
        $matched = $this->findCustomerProductCandidate(
            $customerId,
            $zoneId,
            $aliases,
            $date,
            true
        );

        /*
         * 2) Fallback: customer's older/paused DOI for the same product.
         *    The sheet is the actual-delivery source of truth, so a product
         *    can still be imported even when its DOI date/status is stale.
         */
        if (!$matched) {
            $matched = $this->findCustomerProductCandidate(
                $customerId,
                $zoneId,
                $aliases,
                $date,
                false
            );
        }

        if ($matched) {
            $matched->vendor_id = $matched->item_vendor_id ?? $matched->draft_vendor_id;
            return $matched;
        }

        /*
         * 3) Final fallback: resolve the product/variant globally from DB,
         *    then attach the customer's latest draft order (if one exists).
         *    Price still comes from variants.price.
         */
        $catalog = DB::table('products as p')
            ->join('variants as v', 'v.product_id', '=', 'p.product_id')
            ->select([
                'p.product_id',
                'v.variant_id',
                'p.title as product_title',
                'v.title as variant_title',
                'v.price as current_variant_price',
            ])
            ->get()
            ->first(function ($candidate) use ($aliases): bool {
                return $this->candidateMatchesAliases($candidate, $aliases);
            });

        if (!$catalog) {
            return null;
        }

        $draft = DB::table('draft_orders')
            ->where('customer_id', $customerId)
            ->where('zone_id', $zoneId)
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();

        $itemVendorId = null;
        if ($draft) {
            $itemVendorId = DB::table('draft_order_items')
                ->where('draft_order_id', $draft->id)
                ->where('product_id', $catalog->product_id)
                ->where('variant_id', $catalog->variant_id)
                ->orderByDesc('id')
                ->value('vendor_id');
        }

        return (object) [
            'draft_order_id' => $draft?->id,
            'draft_vendor_id' => $draft?->vendor_id,
            'zone_id' => $draft?->zone_id ?? $zoneId,
            'item_vendor_id' => $itemVendorId,
            'vendor_id' => $itemVendorId ?? $draft?->vendor_id,
            'product_id' => $catalog->product_id,
            'variant_id' => $catalog->variant_id,
            'product_title' => $catalog->product_title,
            'variant_title' => $catalog->variant_title,
            'current_variant_price' => $catalog->current_variant_price,
        ];
    }

    private function findCustomerProductCandidate(
        int $customerId,
        int $zoneId,
        array $aliases,
        string $date,
        bool $enforceDateAndActive
    ): ?object {
        $query = DB::table('draft_order_items as doi')
            ->join('draft_orders as d', 'd.id', '=', 'doi.draft_order_id')
            ->join('products as p', 'p.product_id', '=', 'doi.product_id')
            ->join('variants as v', 'v.variant_id', '=', 'doi.variant_id')
            ->where('d.customer_id', $customerId)
            ->where('d.zone_id', $zoneId);

        if ($enforceDateAndActive) {
            $query
                ->where('doi.status', 'active')
                ->where('doi.qty', '>', 0)
                ->where(function ($q) use ($date): void {
                    $q->whereNull('doi.start_date')->orWhere('doi.start_date', '<=', $date);
                })
                ->where(function ($q) use ($date): void {
                    $q->whereNull('doi.end_date')->orWhere('doi.end_date', '>=', $date);
                });
        }

        return $query
            ->select([
                'd.id as draft_order_id',
                'd.vendor_id as draft_vendor_id',
                'd.zone_id',
                'doi.vendor_id as item_vendor_id',
                'doi.product_id',
                'doi.variant_id',
                'doi.start_date',
                'doi.price_snapshot',
                'p.title as product_title',
                'v.title as variant_title',
                'v.price as current_variant_price',
            ])
            ->orderByRaw("CASE WHEN doi.status = 'active' AND doi.qty > 0 THEN 0 ELSE 1 END")
            ->orderByDesc('doi.start_date')
            ->get()
            ->first(function ($candidate) use ($aliases): bool {
                return $this->candidateMatchesAliases($candidate, $aliases);
            });
    }

    private function candidateMatchesAliases(object $candidate, array $aliases): bool
    {
        $product = $this->normalizeKey((string) $candidate->product_title);
        $variant = $this->normalizeKey((string) $candidate->variant_title);
        $combined = $product . $variant;

        foreach ($aliases as $alias) {
            if (
                $product === $alias ||
                $variant === $alias ||
                str_contains($combined, $alias)
            ) {
                return true;
            }
        }

        return false;
    }

    private function showValidationSummary(array $stats, array $resolved): void
    {
        $amount = round(array_sum(array_column($resolved, 'line_total')), 2);
        $orderCount = collect($resolved)
            ->map(fn(array $r) => $r['customer_id'] . '|' . $r['date'])
            ->unique()
            ->count();

        $this->newLine();
        $this->table(
            ['Validation', 'Count / Amount'],
            [
                ['Positive sheet entries', $stats['sheet_rows']],
                ['Resolved item entries', count($resolved)],
                ['Expected orders', $orderCount],
                ['Expected amount', '₹' . number_format($amount, 2)],
                ['Duplicate sheet keys merged', $stats['duplicate_sheet_keys']],
                ['Customers missing', $stats['customers_missing']],
                ['Customers ambiguous', $stats['customers_ambiguous']],
                ['Products missing', $stats['products_missing']],
                ['Draft orders missing', $stats['draft_orders_missing']],
            ]
        );
    }

    private function normalizeKey(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '', $value) ?? '';
        return $value;
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function toNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        $text = trim((string) $value);
        if ($text === '') {
            return 0.0;
        }

        $text = str_replace([',', '₹'], '', $text);
        return is_numeric($text) ? round((float) $text, 2) : 0.0;
    }
}
