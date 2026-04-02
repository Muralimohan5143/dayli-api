<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VariantPriceService
{
    public function getCurrentPrice(int $variantId): ?float
    {
        $price = DB::table('variants')
            ->where('variant_id', $variantId)
            ->value('price');

        return $price !== null ? (float) $price : null;
    }

    public function getPriceAt(int $variantId, string|\DateTimeInterface $at): ?float
    {
        $at = $at instanceof \DateTimeInterface
            ? Carbon::instance(\DateTime::createFromInterface($at))
            : Carbon::parse($at);

        $row = DB::table('variant_price_history')
            ->where('variant_id', $variantId)
            ->where('effective_from', '<=', $at)
            ->where(function ($q) use ($at) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $at);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $row ? (float) $row->price : null;
    }

    public function updateVariantPrice(
        int $variantId,
        float $newPrice,
        ?int $changedBy = null,
        ?string $note = null,
        ?string $changeSource = 'admin_panel',
        string|\DateTimeInterface|null $effectiveFrom = null
    ): void {
        $effectiveFrom = $effectiveFrom
            ? ($effectiveFrom instanceof \DateTimeInterface
                ? Carbon::instance(\DateTime::createFromInterface($effectiveFrom))
                : Carbon::parse($effectiveFrom))
            : now();

        DB::transaction(function () use (
            $variantId,
            $newPrice,
            $changedBy,
            $note,
            $changeSource,
            $effectiveFrom
        ) {
            $variant = DB::table('variants')
                ->where('variant_id', $variantId)
                ->first();

            if (! $variant) {
                throw new RuntimeException("Variant not found: {$variantId}");
            }

            $currentActive = DB::table('variant_price_history')
                ->where('variant_id', $variantId)
                ->whereNull('effective_to')
                ->orderByDesc('effective_from')
                ->first();

            if ($currentActive && (float) $currentActive->price === (float) $newPrice) {
                DB::table('variants')
                    ->where('variant_id', $variantId)
                    ->update([
                        'price' => $newPrice,
                        'updated_at' => now(),
                    ]);

                return;
            }

            if ($currentActive) {
                DB::table('variant_price_history')
                    ->where('id', $currentActive->id)
                    ->update([
                        'effective_to' => $effectiveFrom->copy()->subSecond(),
                        'updated_at' => now(),
                    ]);
            }

            DB::table('variant_price_history')->insert([
                'product_id' => $variant->product_id,
                'variant_id' => $variantId,
                'price' => $newPrice,
                'effective_from' => $effectiveFrom,
                'effective_to' => null,
                'changed_by' => $changedBy,
                'change_source' => $changeSource,
                'note' => $note,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('variants')
                ->where('variant_id', $variantId)
                ->update([
                    'price' => $newPrice,
                    'updated_at' => now(),
                ]);
        });
    }

    public function backfillCurrentPrices(?int $changedBy = null, ?string $note = 'Initial backfill'): int
    {
        $variants = DB::table('variants')
            ->select('variant_id', 'product_id', 'price')
            ->whereNotNull('price')
            ->get();

        $count = 0;

        DB::transaction(function () use ($variants, $changedBy, $note, &$count) {
            foreach ($variants as $variant) {
                $exists = DB::table('variant_price_history')
                    ->where('variant_id', $variant->variant_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('variant_price_history')->insert([
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->variant_id,
                    'price' => (float) $variant->price,
                    'effective_from' => now(),
                    'effective_to' => null,
                    'changed_by' => $changedBy,
                    'change_source' => 'backfill',
                    'note' => $note,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $count++;
            }
        });

        return $count;
    }
}
