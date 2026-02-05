<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ImportMilkCsv extends Command
{
    protected $signature = 'import:milk-csv
        {csv_path : Full path to clean.csv}
        {--dry-run : Do not write to DB}
        {--by=1 : by_user_id (admin/staff user id)}
        {--default-zone= : If user.zone_id is null, use this zone_id}
        {--default-start= : If Start Date missing, use this YYYY-MM-DD}
    ';

    protected $description = 'Import milk subscriptions from clean.csv into sub_change_requests + draft_orders + draft_order_items';


    private function cleanCsvName(string $name): string
    {
        $name = trim($name);

        // only first line before newline (remove Telugu line)
        $parts = preg_split("/\r\n|\n|\r/", $name);
        $name = $parts[0] ?? $name;

        // remove weird non-printable chars
        $name = preg_replace('/[^\P{C}\n]+/u', '', $name);

        // normalize spaces
        $name = preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }


    public function handle(): int
    {
        $csvPath = $this->argument('csv_path');
        $dryRun  = (bool)$this->option('dry-run');
        $byUser  = (int)$this->option('by');
        $fallbackZoneId = $this->option('default-zone') !== null ? (int)$this->option('default-zone') : null;
        $fallbackStart  = $this->option('default-start') ?: null;

        if (!file_exists($csvPath)) {
            $this->error("CSV not found: {$csvPath}");
            return Command::FAILURE;
        }

        $this->info("Reading CSV: {$csvPath}");
        $rows = $this->readCsvAssoc($csvPath);

        if (count($rows) === 0) {
            $this->warn("CSV has no data rows.");
            return Command::SUCCESS;
        }

        // Expected CSV headers (based on what you pasted)
        // "Name" and "Milk Type" and "Change Qty" are must.
        $ok = 0;
        $skip = 0;

        DB::beginTransaction();

        try {
            foreach ($rows as $idx => $row) {
                $line = $idx + 2; // header = line1

                $nameRaw = (string)($row['Name'] ?? '');
                $name = $this->cleanCsvName($nameRaw);
                $milkType = trim((string)($row['Milk Type'] ?? ''));
                $qtyRaw = $row['Change Qty'] ?? null;

                // Optional fields
                $supplyDay = trim((string)($row['Supply Day'] ?? '')); // ex: daily/alternate/etc (if your csv uses those words)
                $startDate = $this->pickDate($row['Start Date'] ?? null)
                    ?? $this->pickDate($row["Supply Change\nBegin Date"] ?? null)
                    ?? $fallbackStart
                    ?? now()->toDateString();

                $endDate = $this->pickDate($row["Supply Change\nEnd Date"] ?? null);

                // --------------------
                // draft_order_items.status mapping
                // --------------------
                $isActiveRaw = strtolower(trim((string)($row['isActive'] ?? '')));

                // ✅ supports: active/inactive AND y/n AND 1/0
                $isActive = in_array($isActiveRaw, [
                    'active',
                    'y',
                    'yes',
                    'true',
                    '1'
                ], true);

                // ✅ default mapping per your requirement
                $itemStatus = $isActive ? 'active' : 'paused'; // ✅ DB accepts paused

                // end date past => pause
                if (!empty($endDate) && $endDate < now()->toDateString()) {
                    $itemStatus = 'paused';
                }


                $qty = $this->toDecimalQty($qtyRaw);
                if ($qty <= 0) $qty = 1;

                if ($name === '' || $milkType === '') {
                    $skip++;
                    $this->warn("Line {$line}: missing Name/Milk Type");
                    continue;
                }

                // 1) Find user by display_name == Name (case-insensitive)
                $user = DB::table('users')
                    ->select('id', 'display_name', 'zone_id')
                    ->whereRaw('LOWER(TRIM(display_name)) = ?', [mb_strtolower($name)])
                    ->first();

                if (!$user) {
                    // fallback LIKE
                    $user = DB::table('users')
                        ->select('id', 'display_name', 'zone_id')
                        ->whereRaw('LOWER(display_name) LIKE ?', ['%' . mb_strtolower($name) . '%'])
                        ->first();
                }

                if (!$user) {
                    $skip++;
                    $this->warn("Line {$line}: user not found for Name='{$name}'");
                    continue;
                }

                $zoneId = $user->zone_id ? (int)$user->zone_id : $fallbackZoneId;

                // 2) Resolve subscription_sub_type by Milk Type (BEST)
                $subType = DB::table('subscription_sub_types')
                    ->select('id', 'subscription_type_id', 'name', 'slug')
                    ->whereRaw('LOWER(TRIM(name)) = ?', [mb_strtolower($milkType)])
                    ->first();

                if (!$subType) {
                    // fallback LIKE
                    $subType = DB::table('subscription_sub_types')
                        ->select('id', 'subscription_type_id', 'name', 'slug')
                        ->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($milkType) . '%'])
                        ->orderByRaw('LENGTH(name) ASC')
                        ->first();
                }

                // If still not found, we will try to infer subscription_type_id by product match.
                $subscriptionTypeId = $subType ? (int)$subType->subscription_type_id : null;

                // 3) Resolve product + variant from Milk Type (products/variants)
                $pv = $this->findProductVariantByMilkType($milkType);

                if (!$pv) {
                    // If subtype exists but product not found, we can still create SCR + draft_order
                    // But draft_order_items needs product+variant. So we must SKIP.
                    $skip++;
                    $this->warn("Line {$line}: product/variant not found for Milk Type='{$milkType}'");
                    continue;
                }

                // If subscriptionTypeId still null, infer from subscription_types by name contains milk/veg etc (last fallback)
                if (!$subscriptionTypeId) {
                    $subscriptionTypeId = $this->inferSubscriptionTypeIdFromText($milkType, $pv['product_title'], $pv['product_sub_type']);
                }

                if (!$subscriptionTypeId) {
                    $skip++;
                    $this->warn("Line {$line}: subscription_type_id not resolved for Milk Type='{$milkType}'");
                    continue;
                }

                // Map CSV Supply Day => cadence/frequency_type
                $cadence = $this->mapCadence($supplyDay) ?? 'daily';
                $frequencyType = $cadence; // same enums set

                // 4) Create sub_change_request first (required fields!)
                $scrPayload = [
                    'csv_line' => $line,
                    'Name' => $name,
                    'Milk Type' => $milkType,
                    'Change Qty' => $qty,
                    'Supply Day' => $supplyDay,
                    'Start Date' => $startDate,
                    'Supply End Date' => $endDate,
                ];

                if ($dryRun) {
                    $ok++;
                    continue;
                }

                $scrId = DB::table('sub_change_requests')->insertGetId([
                    'for_user_id' => (int)$user->id,
                    'by_user_id' => $byUser,
                    'from_id' => null,
                    'draft_order_id' => null,              // update after draft_orders created
                    'zone_id' => $zoneId,
                    'subscription_type_id' => $subscriptionTypeId,
                    'subtypes_json' => $subType
                        ? json_encode([['id' => (int)$subType->id, 'name' => $subType->name]])
                        : json_encode([['name' => $milkType]]),
                    'custom_frequency_format' => null,
                    'invoice_cycle' => 'monthly',
                    'change_reason' => 'staff-error',      // REQUIRED in your schema (no default)
                    'action' => 'create',
                    'status' => 'pending',
                    'approved_by' => null,
                    'approved_at' => null,
                    'priority' => 3,
                    'payload' => json_encode($scrPayload),
                    'meta' => json_encode(['source' => 'milk-csv-import']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 5) Create draft_order linked to SCR
                $draftOrderId = DB::table('draft_orders')->insertGetId([
                    'change_request_id' => $scrId,
                    'customer_id' => (int)$user->id,
                    'vendor_id' => null,
                    'zone_id' => $zoneId,
                    'cadence' => $cadence,
                    'custom_frequency_format' => null,
                    'invoice_cycle' => 'monthly',
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => 'active',
                    'locked_at' => null,
                    'timezone' => 'Asia/Kolkata',
                    'title' => $milkType,
                    'pricing_policy' => null,
                    'tax_policy' => null,
                    'meta' => json_encode(['source' => 'milk-csv-import']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 6) Create draft_order_item
                DB::table('draft_order_items')->insert([
                    'original_item_id' => null,
                    'change_action' => 'create',
                    'draft_order_id' => $draftOrderId,
                    'product_id' => (int)$pv['product_id'],
                    'variant_id' => (int)$pv['variant_id'],
                    'vendor_id' => null,
                    'frequency_type' => $frequencyType,
                    'qty' => $qty,
                    'unit' => 'pcs',
                    'price_snapshot' => null,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'status' => $itemStatus,   // ✅ USE computed status
                    'meta' => json_encode([
                        'csv_milk_type' => $milkType,
                        'product_title' => $pv['product_title'],
                        'variant_title' => $pv['variant_title'],
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // 7) Update SCR with draft_order_id (since SCR has that column)
                DB::table('sub_change_requests')
                    ->where('id', $scrId)
                    ->update([
                        'draft_order_id' => $draftOrderId,
                        'updated_at' => now(),
                    ]);

                $ok++;
            }

            if ($dryRun) {
                DB::rollBack();
                $this->warn("DRY RUN done → OK={$ok}, SKIPPED={$skip} (no DB writes)");
            } else {
                DB::commit();
                $this->info("IMPORT DONE → OK={$ok}, SKIPPED={$skip}");
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            DB::rollBack();
            $this->error("ERROR: " . $e->getMessage());
            return Command::FAILURE;
        }
    }

    /** ---------------- Helpers ---------------- */

    private function readCsvAssoc(string $path): array
    {
        $fh = fopen($path, 'r');
        if (!$fh) return [];

        $header = fgetcsv($fh);
        if (!$header) return [];

        // trim header keys
        $header = array_map(fn($h) => trim((string)$h), $header);

        $rows = [];
        while (($data = fgetcsv($fh)) !== false) {
            // normalize data count
            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = $data[$i] ?? null;
            }
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    private function toDecimalQty($val): float
    {
        if ($val === null) return 1.0;
        $s = trim((string)$val);
        $s = str_replace(',', '.', $s);
        $n = (float)$s;
        return $n;
    }

    private function pickDate($val): ?string
    {
        if ($val === null) return null;
        $s = trim((string)$val);
        if ($s === '') return null;

        // already YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) return $s;

        // handle DD/MM/YYYY or DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $s, $m)) {
            $dd = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            $mm = str_pad($m[2], 2, '0', STR_PAD_LEFT);
            $yy = $m[3];
            return "{$yy}-{$mm}-{$dd}";
        }

        return null;
    }

    private function mapCadence(string $supplyDay): ?string
    {
        $t = mb_strtolower(trim($supplyDay));
        if ($t === '') return null;

        // Map common values
        if (Str::contains($t, 'daily') || $t === 'd') return 'daily';
        if (Str::contains($t, 'alternate')) return 'alternate_days';
        if (Str::contains($t, 'weekday')) return 'weekdays';
        if (Str::contains($t, 'weekend')) return 'weekends';
        if ($t === 'sat' || Str::contains($t, 'saturday')) return 'sat';
        if ($t === 'sun' || Str::contains($t, 'sunday')) return 'sun';
        if (Str::contains($t, 'custom')) return 'custom';

        return null;
    }

    private function normKey(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = str_replace(['-', '_'], ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    private function findProductVariantByMilkType(string $milkType): ?array
    {
        $raw = $milkType;
        $t = $this->normalizeMilkType($milkType);

        if ($t === '') return null;

        // try LIKE match against title and product_sub_type
        $p = DB::table('products')
            ->select('product_id', 'title', 'product_sub_type')
            ->where(function ($q) use ($t) {
                $q->whereRaw('LOWER(REPLACE(title, "-", " ")) LIKE ?', ['%' . $t . '%'])
                    ->orWhereRaw('LOWER(REPLACE(IFNULL(product_sub_type,""), "-", " ")) LIKE ?', ['%' . $t . '%']);
            })
            ->orderByRaw('LENGTH(title) ASC')
            ->first();

        if ($p) {
            $v = DB::table('variants')
                ->select('variant_id', 'title')
                ->where('product_id', $p->product_id)
                ->orderBy('position')
                ->first();

            if (!$v) return null;

            return [
                'product_id' => (int)$p->product_id,
                'variant_id' => (int)$v->variant_id,
                'product_title' => (string)$p->title,
                'product_sub_type' => (string)($p->product_sub_type ?? ''),
                'variant_title' => (string)($v->title ?? ''),
            ];
        }

        // fallback: try variant title match
        $pv = DB::table('variants')
            ->join('products', 'products.product_id', '=', 'variants.product_id')
            ->select(
                'products.product_id',
                'products.title as product_title',
                'products.product_sub_type',
                'variants.variant_id',
                'variants.title as variant_title'
            )
            ->whereRaw('LOWER(REPLACE(variants.title,"-"," ")) LIKE ?', ['%' . $t . '%'])
            ->orderByRaw('LENGTH(variants.title) ASC')
            ->first();

        if (!$pv) return null;

        return [
            'product_id' => (int)$pv->product_id,
            'variant_id' => (int)$pv->variant_id,
            'product_title' => (string)$pv->product_title,
            'product_sub_type' => (string)($pv->product_sub_type ?? ''),
            'variant_title' => (string)($pv->variant_title ?? ''),
        ];
    }


    private function inferSubscriptionTypeIdFromText(string $milkType): ?int
    {
        // ALL milk-family items go under MILK subscription
        $row = DB::table('subscription_types')
            ->select('id')
            ->whereRaw('LOWER(name) LIKE ?', ['%milk%'])
            ->orWhereRaw('LOWER(IFNULL(slug,"")) LIKE ?', ['%milk%'])
            ->where('status', 'active')
            ->first();

        return $row ? (int)$row->id : null;
    }


    private function normalizeMilkType(string $milkType): string
    {
        $t = mb_strtolower(trim($milkType));
        $t = str_replace(['_', '-'], ' ', $t);
        $t = preg_replace('/\s+/', ' ', $t);

        // common typos
        $t = str_replace('viajaya', 'vijaya', $t);

        // expand abbreviations
        // vijaya tm => vijaya toned milk
        $t = preg_replace('/\btm\b/', 'toned milk', $t);

        // remove size words (we match product, not size)
        $t = str_replace(['small', 'big'], '', $t);
        $t = preg_replace('/\s+/', ' ', $t);

        // if it’s gold/silver etc but no milk/curd word, add milk
        if (
            (str_contains($t, 'gold') || str_contains($t, 'sangam') || str_contains($t, 'vijaya') || str_contains($t, 'arokya'))
            && !str_contains($t, 'milk')
            && !str_contains($t, 'curd')
            && !str_contains($t, 'buttermilk')
        ) {
            $t .= ' milk';
        }

        // fix "cur" usage
        $t = str_replace(' cur ', ' curd ', ' ' . $t . ' ');
        $t = trim($t);

        // skip newspaper
        if (str_contains($t, 'newspaper')) return '';

        return $t;
    }
}
