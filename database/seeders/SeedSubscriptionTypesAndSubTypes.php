<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedSubscriptionTypesAndSubTypes extends Seeder
{
    public function run(): void
    {
        // 1) Seed subscription_types from distinct product_type
        $types = DB::table('products')->select('product_type')->distinct()->pluck('product_type');

        foreach ($types as $typeName) {
            if (!$typeName) continue;
            $typeSlug = Str::slug($typeName);                 // "Milk / Dairy" -> "milk-dairy"

            DB::table('subscription_types')->updateOrInsert(
                ['slug' => $typeSlug],
                [
                    'name'       => $typeName,
                    'status'     => 'active',
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }

        // Map type slug -> id
        $typeIdBySlug = DB::table('subscription_types')->pluck('id', 'slug');

        // 2) Seed subscription_sub_types from distinct (product_type, product_sub_type)
        $pairs = DB::table('products')
            ->whereNotNull('product_sub_type')
            ->where('product_sub_type', '!=', '')
            ->select('product_type', 'product_sub_type')
            ->distinct()
            ->get();

        foreach ($pairs as $row) {
            $typeSlug = Str::slug($row->product_type);
            $typeId   = $typeIdBySlug[$typeSlug] ?? null;
            if (!$typeId) continue;

            $subSlug = (string) $row->product_sub_type;       // already enum-like (e.g. leafy_veg)
            $subName = Str::of($subSlug)->replace('_', ' ')->headline(); // "leafy veg" -> "Leafy Veg"

            DB::table('subscription_sub_types')->updateOrInsert(
                ['slug' => $subSlug],
                [
                    'subscription_type_id' => $typeId,
                    'name'       => (string) $subName,
                    'status'     => 'active',
                    'updated_at' => now(),
                    'created_at' => DB::raw('COALESCE(created_at, NOW())'),
                ]
            );
        }
    }
}
