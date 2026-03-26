<?php

namespace App\Services\Imports;

use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\SubChangeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMergedMilkSheetService
{
    /**
     * Rules locked from your sheet logic:
     * - Jan columns = CW -> BS  (Day 1 -> Day 31)
     * - Feb columns = BQ -> AP  (Day 1 -> Day 28)
     * - Mar columns = AO -> Y   (Day 1 -> Day 17)
     * - blank = pause (qty = 0)
     * - positive number = active qty for that day
     * - merge continuous same-qty ranges into one DOI
     * - ignore leading blank/zero days until first positive day
     * - first positive day becomes import start date
     */
    public function import(string $filePath, array $context = []): array
    {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = $spreadsheet->getActiveSheet();

        $rows = $sheet->toArray(null, true, true, false);

        $headerRowIndex = $this->findHeaderRowIndex($rows);
        if ($headerRowIndex === null) {
            throw new \RuntimeException('Header row with Name / Ask Count not found.');
        }
        $headerRow = $rows[$headerRowIndex];
        $columnIndexes = $this->findColumnIndexes($headerRow);
        $dateMap = $this->buildDateColumnMapFromHeader($headerRow);

        $summary = [
            'processed' => 0,
            'created_scr' => 0,
            'created_do' => 0,
            'created_doi' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        DB::transaction(function () use ($rows, $headerRowIndex, $context, &$summary, $columnIndexes, $dateMap) {
            $dataRows = array_slice($rows, $headerRowIndex + 1);

            foreach ($dataRows as $excelRowNumberZeroBased => $row) {
                $excelRowNumber = $headerRowIndex + 2 + $excelRowNumberZeroBased;

                try {
                    $parsed = $this->parseCustomerRow($row, $excelRowNumber, $columnIndexes);

                    if (! $parsed) {
                        continue;
                    }


                    $segments = $this->buildSegments($row, $dateMap, $parsed['frequency_type']);

                    if (empty($segments)) {
                        $summary['skipped']++;
                        continue;
                    }

                    $resolved = $this->resolveReferences($parsed, $context);

                    $baseScr = null;
                    $draftOrder = null;
                    $prevQty = null;

                    $baseScr = SubChangeRequest::query()
                        ->where('for_user_id', $resolved['for_user_id'])
                        ->where('subscription_type_id', $resolved['subscription_type_id'])
                        ->first();

                    if (! $baseScr) {
                        $baseScr = SubChangeRequest::create([
                            'for_user_id' => $resolved['for_user_id'],
                            'by_user_id' => $resolved['by_user_id'],
                            'party_type' => 'consumer',
                            'from_id' => null,
                            'draft_order_id' => null,
                            'zone_id' => $resolved['zone_id'],
                            'subscription_type_id' => $resolved['subscription_type_id'],
                            'subtypes_json' => null,
                            'invoice_cycle' => 'monthly',
                            'change_reason' => 'staff-error',
                            'action' => 'create',
                            'status' => 'approved',
                            'meta' => [
                                'source' => 'merged_milk_sheet_import',
                                'source_excel_row' => $excelRowNumber,
                                'customer_name' => $parsed['name'],
                                'phone' => $parsed['phone'],
                            ],
                            'payload' => null,
                        ]);

                        $summary['created_scr']++;
                    }

                    $summary['created_scr']++;
                    $draftOrder = DraftOrder::query()
                        ->where('change_request_id', $baseScr->id)
                        ->first();

                    if (! $draftOrder) {
                        $draftOrder = DraftOrder::create([
                            'change_request_id' => $baseScr->id,
                            'customer_id' => $resolved['customer_id'],
                            'zone_id' => $resolved['zone_id'],
                            'cadence' => $parsed['frequency_type'],
                            'start_date' => $segments[0]['start_date']->toDateString(),
                            'end_date' => null,
                            'invoice_cycle' => 'monthly',
                        ]);

                        $summary['created_do']++;

                        $baseScr->update([
                            'draft_order_id' => $draftOrder->id,
                        ]);
                    }

                    $prevQty = null;

                    foreach ($segments as $index => $segment) {

                        $action = $this->determineAction($index, $segment['qty'], $prevQty);

                        DraftOrderItem::create([
                            'original_item_id' => null,
                            'change_action' => $action,
                            'draft_order_id' => $draftOrder->id,
                            'product_id' => $resolved['product_id'],
                            'variant_id' => $resolved['variant_id'],
                            'vendor_id' => $resolved['vendor_id'],
                            'frequency_type' => $parsed['frequency_type'],
                            'qty' => $segment['qty'],
                            'unit' => 'pcs',
                            'price_snapshot' => $resolved['price_snapshot'],
                            'start_date' => $segment['start_date']->toDateString(),
                            'end_date' => $segment['end_date']?->toDateString(),
                            'status' => $segment['qty'] > 0 ? 'active' : 'paused',
                            'meta' => [
                                'source' => 'merged_milk_sheet_import',
                                'source_excel_row' => $excelRowNumber,
                                'customer_name' => $parsed['name'],
                                'milk' => $parsed['milk'],
                                'base_ask_count' => $parsed['ask_count'],
                            ],
                        ]);

                        $summary['created_doi']++;

                        $prevQty = $segment['qty'];
                    }

                    $summary['processed']++;
                } catch (\Throwable $e) {
                    $summary['errors'][] = [
                        'row' => $excelRowNumber,
                        'message' => $e->getMessage(),
                    ];
                }
            }
        });

        return $summary;
    }

    protected function findHeaderRowIndex(array $rows): ?int
    {
        foreach ($rows as $i => $row) {
            $normalized = array_map(function ($value) {
                return strtolower(trim((string) $value));
            }, $row);

            $hasName = in_array('name', $normalized, true);

            $hasAskCount = false;
            foreach ($normalized as $cell) {
                if (str_contains($cell, 'ask') && str_contains($cell, 'count')) {
                    $hasAskCount = true;
                    break;
                }
            }

            if ($hasName && $hasAskCount) {
                return $i;
            }
        }

        return null;
    }
    protected function findColumnIndexes(array $headerRow): array
    {
        $indexes = [
            'name' => null,
            'phone' => null,
            'address' => null,
            'plot_no' => null,
            'milk' => null,
            'ask_count' => null,
            'frequency_type' => null,
        ];
        foreach ($headerRow as $index => $value) {
            $cell = strtolower(trim((string) $value));
            $cell = str_replace(["\n", "\r"], ' ', $cell);
            $cell = preg_replace('/\s+/', ' ', $cell);

            if ($cell === 'name') {
                $indexes['name'] = $index;
            } elseif (str_contains($cell, 'phone')) {
                $indexes['phone'] = $index;
            } elseif ($cell === 'address') {
                $indexes['address'] = $index;
            } elseif (str_contains($cell, 'plot')) {
                $indexes['plot_no'] = $index;
            } elseif ($cell === 'milk') {
                $indexes['milk'] = $index;
            } elseif (str_contains($cell, 'ask') && str_contains($cell, 'count')) {
                $indexes['ask_count'] = $index;
            } elseif (
                $cell === 'frequency_type' ||
                $cell === 'frequency type' ||
                str_contains($cell, 'frequency')
            ) {
                $indexes['frequency_type'] = $index;
            }
        }

        if (
            $indexes['name'] === null ||
            $indexes['milk'] === null ||
            $indexes['ask_count'] === null
        ) {
            throw new \RuntimeException('Required columns not found in customer header row.');
        }

        return $indexes;
    }

    protected function parseCustomerRow(array $row, int $excelRowNumber, array $columnIndexes): ?array
    {
        $name = trim((string)($row[$columnIndexes['name']] ?? ''));
        $phone = trim((string)($row[$columnIndexes['phone']] ?? ''));
        $address = trim((string)($row[$columnIndexes['address']] ?? ''));
        $plotNo = trim((string)($row[$columnIndexes['plot_no']] ?? ''));
        $milk = trim((string)($row[$columnIndexes['milk']] ?? ''));
        $askCount = $row[$columnIndexes['ask_count']] ?? null;
        $frequencyTypeRaw = trim((string)($row[$columnIndexes['frequency_type']] ?? 'daily'));
        $frequencyType = $this->normalizeFrequencyType($frequencyTypeRaw);

        if ($name === '' || strtolower($name) === 'name') {
            return null;
        }

        if ($milk === '' || strtolower($milk) === 'milk') {
            return null;
        }

        if (! is_numeric($askCount)) {
            return null;
        }

        return [
            'excel_row' => $excelRowNumber,
            'name' => $name,
            'phone' => $phone,
            'address' => $address,
            'plot_no' => $plotNo,
            'milk' => $milk,
            'ask_count' => (float) $askCount,
            'frequency_type' => $frequencyType,
        ];
    }

    protected function normalizeFrequencyType(?string $value): string
    {
        $value = strtolower(trim((string) $value));
        $value = str_replace([' ', '-'], '_', $value);

        $allowed = [
            'daily',
            'alternate_days',
            'weekdays',
            'weekends',
            'sat',
            'sun',
            'custom',
            'on_demand',
        ];

        if (in_array($value, $allowed, true)) {
            return $value;
        }

        return 'daily';
    }
    protected function buildSegments(array $row, array $dateMap, string $frequencyType = 'daily'): array
    {
        $dailyStates = [];

        // ✅ STEP 1 — build using DATE KEY (no duplicates)
        foreach ($dateMap as $colIndex => $date) {
            $value = is_numeric($colIndex) ? ($row[$colIndex] ?? null) : null;
            if ($value === null || $value === '') {
                $qty = 0.0; // ✅ blank = pause
            } else {
                $qty = $this->normalizeQty($value);
            }

            $key = $date->toDateString();

            if (isset($dailyStates[$key])) {
                continue; // skip duplicate same date
            }

            $dailyStates[$key] = [
                'date' => $date,
                'qty'  => $qty,
            ];
        }

        if (empty($dailyStates)) {
            return [];
        }
        if ($frequencyType !== 'daily') {
            $firstActiveDate = null;
            $lastActiveDate = null;
            $maxQty = 0.0;

            foreach ($dailyStates as $state) {
                $qty = (float)($state['qty'] ?? 0);

                if ($qty > 0) {
                    if ($firstActiveDate === null) {
                        $firstActiveDate = $state['date']->copy();
                    }

                    $lastActiveDate = $state['date']->copy();
                    $maxQty = max($maxQty, $qty);
                }
            }

            if ($firstActiveDate === null) {
                return [[
                    'start_date' => $dailyStates[0]['date'],
                    'end_date' => null,
                    'qty' => 0.0,
                ]];
            }

            return [[
                'start_date' => $firstActiveDate,
                'end_date' => $lastActiveDate,
                'qty' => $maxQty > 0 ? $maxQty : 1.0,
            ]];
        }

        // ✅ STEP 2 — sort by date
        ksort($dailyStates);
        $dailyStates = array_values($dailyStates);

        // ✅ STEP 3 — fill missing dates (VERY IMPORTANT)
        $filled = [];
        $start = $dailyStates[0]['date']->copy();
        $end   = end($dailyStates)['date']->copy();

        $map = [];
        foreach ($dailyStates as $d) {
            $map[$d['date']->toDateString()] = $d['qty'];
        }

        for ($d = $start; $d->lte($end); $d->addDay()) {
            $key = $d->toDateString();

            $filled[] = [
                'date' => $d->copy(),
                'qty'  => $map[$key] ?? 0.0,
            ];
        }

        $dailyStates = $filled;


        // ✅ STEP 4 — remove 1-day noise

        // ✅ STEP 5 — segmentation
        $segments = [];

        $currentStart = $dailyStates[0]['date'];
        $lastDate     = $dailyStates[0]['date'];
        $currentQty = $dailyStates[0]['qty'];

        for ($i = 1; $i < count($dailyStates); $i++) {
            $day = $dailyStates[$i];


            if ($currentQty === null) {
                $currentStart = $day['date'];
                $currentQty = $day['qty'];
                continue;
            }

            if ($day['qty'] !== $currentQty) {
                $segments[] = [
                    'start_date' => $currentStart,
                    'end_date'   => $lastDate,
                    'qty'        => $currentQty,
                ];

                $currentStart = $day['date'];
                $currentQty   = $day['qty'];
            }

            $lastDate = $day['date'];
        }

        $segments[] = [
            'start_date' => $currentStart,
            'end_date'   => null,
            'qty'        => $currentQty,
        ];

        return $segments;
    }
    protected function buildDateColumnMapFromHeader(array $headerRow): array
    {
        $map = [];

        // JAN block
        // Day 1 = col 100 ... Day 31 = col 70
        for ($day = 1; $day <= 31; $day++) {
            $colIndex = 100 - $day; // 1->100, 31->70
            $map[$colIndex] = Carbon::create(2026, 1, $day);
        }

        // FEB block
        // Day 1 = col 68 ... Day 28 = col 41
        for ($day = 1; $day <= 28; $day++) {
            $colIndex = 69 - $day; // 1->68, 28->41
            $map[$colIndex] = Carbon::create(2026, 2, $day);
        }

        // MAR block
        // Day 1 = col 40 ... Day 17 = col 24
        for ($day = 1; $day <= 17; $day++) {
            $colIndex = 41 - $day; // 1->40, 17->24
            $map[$colIndex] = Carbon::create(2026, 3, $day);
        }

        return $map;
    }
    protected function normalizeQty(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null; // 🔥 ignore
        }

        if (!is_numeric($value)) {
            return null;
        }

        $qty = (float) $value;

        return $qty > 0 ? $qty : 0.0;
    }

    protected function determineAction(int $index, float $currentQty, ?float $prevQty): string
    {
        if ($index === 0) {
            return 'create';
        }

        if ($currentQty == 0.0) {
            return 'pause';
        }

        if ($prevQty == 0.0 && $currentQty > 0) {
            return 'resume';
        }

        return 'modify';
    }

    protected function determineChangeReason(string $action): string
    {
        return match ($action) {
            'create' => 'staff-error',
            'pause' => 'staff-error',
            'resume' => 'staff-error',
            'modify' => 'staff-error',
            default => 'staff-error',
        };
    }

    /**
     * Replace this with your actual lookups.
     */
    protected function resolveReferences(array $parsed, array $context): array
    {
        $phone = $this->normalizePhone($parsed['phone']);

        if ($phone === '') {
            throw new \RuntimeException("Phone empty for customer: {$parsed['name']}");
        }

        $user = DB::table('users')
            ->where('phone', $phone)
            ->first();

        if (! $user) {
            throw new \RuntimeException("User not found for phone: {$phone}");
        }

        $subscriptionTypeId = $this->resolveSubscriptionTypeId($context);

        $pv = $this->findProductVariantByMilkName($parsed['milk']);

        logger()->info('MILK_MAP_DEBUG', [
            'customer' => $parsed['name'],
            'phone' => $parsed['phone'],
            'raw_milk' => $parsed['milk'],
            'normalized_milk' => $this->normalizeMilkName($parsed['milk']),
            'product_id' => $pv['product_id'] ?? null,
            'variant_id' => $pv['variant_id'] ?? null,
            'product_title' => $pv['title'] ?? null,
            'variant_title' => $pv['variant_title'] ?? null,
        ]);

        if (! $pv) {
            throw new \RuntimeException("Product mapping not found for milk: {$parsed['milk']}");
        }

        return [
            'for_user_id' => (int) $user->id,
            'customer_id' => (int) $user->id,
            'by_user_id' => (int) ($context['by_user_id'] ?? 1),
            'zone_id' => (int) ($context['zone_id'] ?? 1),
            'subscription_type_id' => (int) $subscriptionTypeId,
            'product_id' => (int) $pv['product_id'],
            'variant_id' => (int) $pv['variant_id'],
            'vendor_id' => $context['vendor_id'] ?? null,
            'price_snapshot' => $pv['unit_price'] !== null ? (float) $pv['unit_price'] : null,
        ];
    }

    protected function resolveSubscriptionTypeId(array $context): int
    {
        if (! empty($context['subscription_type_id'])) {
            return (int) $context['subscription_type_id'];
        }

        $row = DB::table('subscription_types')
            ->where(function ($q) {
                $q->whereRaw('LOWER(name) = ?', ['milk'])
                    ->orWhereRaw('LOWER(slug) = ?', ['milk'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%milk%'])
                    ->orWhereRaw('LOWER(slug) LIKE ?', ['%milk%']);
            })
            ->first();

        if (! $row) {
            throw new \RuntimeException('Milk subscription type not found.');
        }

        return (int) $row->id;
    }
    protected function findProductVariantByMilkName(string $milkName): ?array
    {
        $normalizedInput = $this->normalizeMilkName($milkName);

        // STEP 1: exact product title match first
        $products = DB::table('products')
            ->select('product_id', 'title')
            ->get();

        foreach ($products as $product) {
            if ($this->normalizeMilkName((string) $product->title) === $normalizedInput) {
                $variants = DB::table('variants')
                    ->select('variant_id', 'product_id', 'title', 'price', 'position')
                    ->where('product_id', $product->product_id)
                    ->get();

                // first try exact variant title match
                foreach ($variants as $variant) {
                    $combined = $this->normalizeMilkName(
                        (string) $product->title . ' ' . (string) $variant->title
                    );

                    if (
                        $this->normalizeMilkName((string) $variant->title) === $normalizedInput ||
                        $combined === $normalizedInput
                    ) {
                        return [
                            'product_id' => $product->product_id,
                            'variant_id' => $variant->variant_id,
                            'title' => $product->title,
                            'variant_title' => $variant->title,
                            'unit_price' => $variant->price,
                        ];
                    }
                }

                // fallback:
                // if input contains 500ml, prefer 500ml variant
                // otherwise prefer non-500ml / default variant
                $wants500 = str_contains($normalizedInput, '500ml') || str_contains($normalizedInput, '500 ml');

                $variant = collect($variants)
                    ->sortBy('position')
                    ->first(function ($v) use ($wants500) {
                        $vt = $this->normalizeMilkName((string) $v->title);

                        if ($wants500) {
                            return str_contains($vt, '500ml') || str_contains($vt, '500 ml');
                        }

                        return ! str_contains($vt, '500ml') && ! str_contains($vt, '500 ml');
                    });

                if (! $variant) {
                    $variant = collect($variants)->sortBy('position')->first();
                }

                if ($variant) {
                    return [
                        'product_id' => $product->product_id,
                        'variant_id' => $variant->variant_id,
                        'title' => $product->title,
                        'variant_title' => $variant->title,
                        'unit_price' => $variant->price,
                    ];
                }
            }
        }

        // STEP 2: exact variant title match
        $variants = DB::table('variants')
            ->join('products', 'products.product_id', '=', 'variants.product_id')
            ->select(
                'products.product_id',
                'products.title as product_title',
                'variants.variant_id',
                'variants.title as variant_title',
                'variants.price'
            )
            ->get();

        foreach ($variants as $pv) {
            $combined = $this->normalizeMilkName(
                (string) $pv->product_title . ' ' . (string) $pv->variant_title
            );

            if (
                $this->normalizeMilkName((string) $pv->variant_title) === $normalizedInput ||
                $combined === $normalizedInput
            ) {
                return [
                    'product_id' => $pv->product_id,
                    'variant_id' => $pv->variant_id,
                    'title' => $pv->product_title,
                    'variant_title' => $pv->variant_title,
                    'unit_price' => $pv->price,
                ];
            }
        }

        // STEP 3: fallback search terms
        $terms = $this->milkSearchTerms($milkName);

        foreach ($terms as $term) {
            $product = DB::table('products')
                ->select('product_id', 'title')
                ->whereRaw('LOWER(title) LIKE ?', ['%' . $term . '%'])
                ->orderByRaw('LENGTH(title) ASC')
                ->first();

            if ($product) {
                $variants = DB::table('variants')
                    ->select('variant_id', 'product_id', 'title', 'price', 'position')
                    ->where('product_id', $product->product_id)
                    ->get();

                foreach ($variants as $variant) {
                    $combined = $this->normalizeMilkName(
                        (string) $product->title . ' ' . (string) $variant->title
                    );

                    if (
                        $this->normalizeMilkName((string) $variant->title) === $normalizedInput ||
                        $combined === $normalizedInput
                    ) {
                        return [
                            'product_id' => $product->product_id,
                            'variant_id' => $variant->variant_id,
                            'title' => $product->title,
                            'variant_title' => $variant->title,
                            'unit_price' => $variant->price,
                        ];
                    }
                }

                $variant = $variants->sortBy('position')->first();

                if ($variant) {
                    return [
                        'product_id' => $product->product_id,
                        'variant_id' => $variant->variant_id,
                        'title' => $product->title,
                        'variant_title' => $variant->title,
                        'unit_price' => $variant->price,
                    ];
                }
            }
        }

        foreach ($terms as $term) {
            $pv = DB::table('variants')
                ->join('products', 'products.product_id', '=', 'variants.product_id')
                ->select(
                    'products.product_id',
                    'products.title as product_title',
                    'variants.variant_id',
                    'variants.title as variant_title',
                    'variants.price'
                )
                ->where(function ($q) use ($term) {
                    $q->whereRaw('LOWER(variants.title) LIKE ?', ['%' . $term . '%'])
                        ->orWhereRaw(
                            "LOWER(CONCAT(products.title, ' ', variants.title)) LIKE ?",
                            ['%' . $term . '%']
                        );
                })
                ->orderByRaw('LENGTH(variants.title) ASC')
                ->first();

            if ($pv) {
                return [
                    'product_id' => $pv->product_id,
                    'variant_id' => $pv->variant_id,
                    'title' => $pv->product_title,
                    'variant_title' => $pv->variant_title,
                    'unit_price' => $pv->price,
                ];
            }
        }

        return null;
    }
    protected function milkSearchTerms(string $milkName): array
    {
        $normalized = $this->normalizeMilkName($milkName);

        $terms = [$normalized];

        $map = [
            'vijaya gold' => ['vijaya gold', 'vijaya-gold', 'gold milk'],
            'vijaya tm' => ['vijaya tm', 'vijaya-tm', 'tm milk'],
            'vijaya tm small' => ['vijaya tm small', 'vijaya-tm-small', 'tm small'],
            'arokya gold' => ['arokya gold', 'arokya-gold'],
            'vijaya curd' => ['vijaya curd', 'vijaya-curd'],
            'vijaya curd small' => ['vijaya curd small', 'vijaya-curd-small'],
            'hatsun curd' => ['hatsun curd', 'hatsun-curd'],
            'hatsun curd small' => ['hatsun curd small', 'hatsun-curd-small'],
            'vijaya toned milk' => ['vijaya toned milk', 'vijaya toned', 'toned milk'],
            'vijaya toned milk 500ml' => ['vijaya toned milk 500ml', 'vijaya toned 500ml', 'toned milk 500ml'],
        ];

        foreach ($map as $key => $aliases) {
            if (str_contains($normalized, $key)) {
                $terms = array_merge($terms, $aliases);
            }
        }

        $terms = array_map(fn($x) => strtolower(trim($x)), $terms);
        $terms = array_values(array_unique(array_filter($terms)));

        return $terms;
    }
    protected function normalizeMilkName(string $milkName): string
    {
        $milkName = strtolower($milkName);

        // remove telugu/non-ascii noise if present
        $milkName = preg_replace('/[^\x20-\x7E]/u', ' ', $milkName);

        // make brackets behave like spaces
        $milkName = str_replace(['(', ')', '[', ']', '{', '}'], ' ', $milkName);

        $milkName = str_replace(['_', '/', '\\'], ' ', $milkName);
        $milkName = str_replace('-', ' ', $milkName);
        $milkName = preg_replace('/\s+/', ' ', $milkName);

        return trim($milkName);
    }
    protected function normalizePhone(?string $phone): string
    {
        $phone = (string) $phone;
        $phone = preg_replace('/\D+/', '', $phone ?? '');

        if ($phone === '') {
            return '';
        }

        // keep last 10 digits for Indian mobile numbers
        if (strlen($phone) > 10) {
            $phone = substr($phone, -10);
        }

        return $phone;
    }




    /**
     * Returns [columnIndex => CarbonDate]
     *
     * Zero-based indexes from PhpSpreadsheet toArray():
     * Jan: CW->BS => 108..78 => Jan 1..31
     * Feb: BQ->AP => 76..49 => Feb 1..28
     * Mar: AO->Y  => 48..32 => Mar 1..17
     */
    protected function dateColumnMap(): array
    {
        $map = [];

        foreach (range(108, 78) as $offset => $colIndex) {
            $map[$colIndex] = Carbon::create(2026, 1, $offset + 1);
        }

        foreach (range(76, 49) as $offset => $colIndex) {
            $map[$colIndex] = Carbon::create(2026, 2, $offset + 1);
        }

        foreach (range(48, 32) as $offset => $colIndex) {
            $map[$colIndex] = Carbon::create(2026, 3, $offset + 1);
        }

        ksort($map);

        // We must return chronological Jan1 -> Mar17, not sorted by column index
        $chronological = [];

        foreach (range(108, 78) as $offset => $colIndex) {
            $chronological[$colIndex] = Carbon::create(2026, 1, $offset + 1);
        }
        foreach (range(76, 49) as $offset => $colIndex) {
            $chronological[$colIndex] = Carbon::create(2026, 2, $offset + 1);
        }
        foreach (range(48, 32) as $offset => $colIndex) {
            $chronological[$colIndex] = Carbon::create(2026, 3, $offset + 1);
        }

        return $chronological;
    }
}
