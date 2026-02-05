<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BootstrapZoneProductVariants extends Command
{
    protected $signature = 'dayli:bootstrap-zone-products
        {--zones= : Comma-separated zone IDs, e.g. 1,2}
        {--product_type= : Filter by product_type}
        {--tag= : Filter by a tag (CSV, case-insensitive)}
        {--dry : Dry-run (no writes)}
        {--products=products : Products table name}
        {--variants=variants : Variants table name}                   
        {--product-id-col= : Override product PK column}
        {--product-title-col= : Override product title column}
        {--product-type-col= : Override product_type column}
        {--product-tags-col= : Override tags column}
        {--variant-id-col= : Override variant PK column}
        {--variant-product-id-col= : Override variants->product FK}';

    protected $description = 'Populate zone_product_variants from products & variants.';

    public function handle(): int
    {
        $zones = array_values(array_filter(array_map('trim', explode(',', (string)$this->option('zones')))));
        if (empty($zones)) { $this->error('Please pass --zones=1 or --zones=1,2'); return self::FAILURE; }

        $tblP = (string)$this->option('products');
        $tblV = (string)$this->option('variants');
        if (!Schema::hasTable($tblP)) { $this->error("Products table '{$tblP}' not found."); return self::FAILURE; }

        [$pId, $pTitle, $pType, $pTags] = $this->detectProductCols($tblP);
        [$vId, $vPid] = $this->detectVariantCols($tblV, $pId);

        $pId    = $this->option('product-id-col')        ?: $pId;
        $pTitle = $this->option('product-title-col')     ?: $pTitle;
        $pType  = $this->option('product-type-col')      ?: $pType;
        $pTags  = $this->option('product-tags-col')      ?: $pTags;
        $vId    = $this->option('variant-id-col')        ?: $vId;
        $vPid   = $this->option('variant-product-id-col') ?: $vPid;

        $dry    = (bool)$this->option('dry');
        $typeFilter = $this->option('product_type');
        $tagFilter  = $this->option('tag');

        // Build product query
        $selectCols = array_values(array_filter([$pId, $pTitle, $pType, $pTags]));
        $productsQ = DB::table($tblP)->select($selectCols);
        if ($typeFilter && $pType && Schema::hasColumn($tblP, $pType)) {
            $productsQ->where($pType, $typeFilter);
        }
        $products = $productsQ->get();

        if ($products->isEmpty()) { $this->warn('No products found with current filters.'); return self::SUCCESS; }

        // Tag filter
        if ($tagFilter && $pTags && Schema::hasColumn($tblP, $pTags)) {
            $needle = mb_strtolower(trim($tagFilter));
            $products = $products->filter(function ($row) use ($pTags, $needle) {
                $tagsStr = mb_strtolower((string)($row->{$pTags} ?? ''));
                $tags = preg_split('/\s*,\s*/', $tagsStr, -1, PREG_SPLIT_NO_EMPTY);
                return in_array($needle, $tags, true);
            })->values();
        }

        if ($products->isEmpty()) { $this->warn('No products matched after tag/product_type filters.'); return self::SUCCESS; }

        $productIds = $products->pluck($pId)->filter()->unique()->values();

        // Variants (optional)
        $variants = collect();
        $variantsEnabled = Schema::hasTable($tblV) && $vId && $vPid
            && Schema::hasColumn($tblV, $vId) && Schema::hasColumn($tblV, $vPid);

        if ($variantsEnabled && $productIds->isNotEmpty()) {
            $variants = DB::table($tblV)->select([$vId, $vPid])->whereIn($vPid, $productIds)->get();
        }

        $productsWithVariants    = $variants->pluck($vPid)->unique();
        $productsWithoutVariants = $productIds->diff($productsWithVariants);

        $this->info("Products total: {$products->count()} | With variants: {$productsWithVariants->count()} | Without variants: {$productsWithoutVariants->count()}");

        $now = now();
        $rows = [];
        $hasVariantKeyCol = Schema::hasTable('zone_product_variants') && Schema::hasColumn('zone_product_variants', 'variant_key');

        // With variants
        foreach ($variants as $v) {
            $pid = (int)$v->{$vPid};
            $vid = (int)$v->{$vId};
            foreach ($zones as $z) {
                $row = [
                    'zone_id'    => (int)$z,
                    'product_id' => $pid,
                    'variant_id' => $vid,
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($hasVariantKeyCol) $row['variant_key'] = $vid; // only if column exists
                $rows[] = $row;
            }
        }

        // Without variants
        foreach ($productsWithoutVariants as $pid) {
            foreach ($zones as $z) {
                $row = [
                    'zone_id'    => (int)$z,
                    'product_id' => (int)$pid,
                    'variant_id' => null,
                    'is_active'  => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if ($hasVariantKeyCol) $row['variant_key'] = 0;
                $rows[] = $row;
            }
        }

        $this->info('Rows to upsert: ' . count($rows));
        if ($dry || empty($rows)) { if ($dry) $this->line('Dry-run: no writes performed.'); return self::SUCCESS; }

        foreach (array_chunk($rows, 1000) as $chunk) {
            try {
                DB::table('zone_product_variants')->upsert(
                    $chunk,
                    ['zone_id', 'product_id', 'variant_id'],
                    ['is_active', 'updated_at']
                );
            } catch (\Throwable $e) {
                // Fallback path
                foreach ($chunk as $r) {
                    try {
                        DB::table('zone_product_variants')->insertOrIgnore($r);
                        // manual update for existing row
                        $q = DB::table('zone_product_variants')
                              ->where('zone_id', $r['zone_id'])
                              ->where('product_id', $r['product_id']);
                        if (is_null($r['variant_id'])) {
                            $q->whereNull('variant_id');
                        } else {
                            $q->where('variant_id', $r['variant_id']);
                        }
                        $q->update(['is_active' => $r['is_active'], 'updated_at' => $now]);
                    } catch (\Throwable $e2) {
                        // keep going
                    }
                }
            }
        }

        $this->info('zone_product_variants populated ✅');
        return self::SUCCESS;
    }

    // --- helpers ---

    private function detectProductCols(string $table): array
    {
        $cols = Schema::getColumnListing($table);
        $idCol    = $this->firstExisting($cols, ['product_id','id','shopify_id','gid','legacy_resource_id']);
        $titleCol = $this->firstExisting($cols, ['title','name']);
        $typeCol  = $this->firstExisting($cols, ['product_type','type','category']);
        $tagsCol  = $this->firstExisting($cols, ['tags','tag_list','labels']);
        if (!$idCol) throw new \RuntimeException("Could not detect a product ID column in '{$table}'.");
        return [$idCol, $titleCol, $typeCol, $tagsCol];
    }

    private function detectVariantCols(string $variantsTable, string $productIdCol): array
    {
        if (!Schema::hasTable($variantsTable)) return [null, null];
        $cols = Schema::getColumnListing($variantsTable);
        $vidCol  = $this->firstExisting($cols, ['variant_id','id','shopify_variant_id','gid','legacy_resource_id']);
        $vpidCol = $this->firstExisting($cols, [$productIdCol,'product_id','productid','shopify_product_id','parent_id']);
        return ($vidCol && $vpidCol) ? [$vidCol, $vpidCol] : [null, null];
    }

    private function firstExisting(array $cols, array $candidates): ?string
    {
        $lower = array_change_key_case(array_flip($cols), CASE_LOWER); // values => index
        foreach ($candidates as $cand) {
            $key = strtolower($cand);
            if (isset($lower[$key])) {
                // return the original-cased column name
                return $cols[$lower[$key]];
            }
        }
        return null;
    }
}
