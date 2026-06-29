<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileHomeController extends Controller
{
    public function index(Request $request)
    {
        $zoneId = (int) $request->query('zone_id', 1);

        $zone = DB::table('zones')
            ->where('id', $zoneId)
            ->first();

        $base = DB::table('zone_product_variants as zpv')
            ->join('products as p', 'p.product_id', '=', 'zpv.product_id')
            ->leftJoin('variants as v', 'v.variant_id', '=', 'zpv.variant_id')
            ->where('zpv.zone_id', $zoneId)
            ->where('zpv.is_active', 1)
            ->whereNull('zpv.deleted_at')
            ->whereNotNull('p.img_src')
            ->where('p.img_src', '!=', '')
            ->select([
                'p.product_id',
                'p.title',
                'p.product_type',
                'p.product_sub_type',
                'p.tags',
                'p.img_src',
                'v.variant_id',
                'v.title as variant_title',
                'v.price',
                'v.compare_at_price',
                'v.weight',
                'v.weight_unit',
                'v.inventory_quantity',
            ]);

        return response()->json([
            'success' => true,

            'location' => [
                'zone_id' => $zoneId,
                'zone_name' => $zone?->name ?? 'Dayli Zone',
                'delivery_text' => 'Delivering daily essentials',
            ],

            'banners' => [
                [
                    'title' => 'Fresh Milk Before 6 AM',
                    'subtitle' => 'Daily milk subscription at your doorstep',
                    'type' => 'Milk & Dairy',
                ],
                [
                    'title' => 'Farm Fresh Vegetables',
                    'subtitle' => 'Fresh vegetables delivered daily',
                    'type' => 'Vegetables',
                ],
                [
                    'title' => 'Pooja Essentials & Flowers',
                    'subtitle' => 'Flowers, oils, camphor and samagri',
                    'type' => 'Puja Samagri',
                ],
            ],

            'categories' => [
                ['title' => 'Milk', 'type' => 'Milk & Dairy'],
                ['title' => 'Vegetables', 'type' => 'Vegetables'],
                ['title' => 'Fruits', 'type' => 'Fruits'],
                ['title' => 'Groceries', 'type' => 'Groceries'],
                ['title' => 'Pooja', 'type' => 'Puja Samagri'],
                ['title' => 'Flowers', 'type' => 'Flowers'],

                ['title' => 'Bakery', 'type' => 'Bakery & Snacks'],
                ['title' => 'Beverages', 'type' => 'Beverages'],
                ['title' => 'Sweets', 'type' => 'Sweets & Confectionery'],
                ['title' => 'Snacks', 'type' => 'Chaats & Quick Snacks'],
                ['title' => 'Fish', 'type' => 'Fish & Seafood'],
                ['title' => 'Poultry', 'type' => 'Poultry'],
                ['title' => 'Meat', 'type' => 'Meat'],
                ['title' => 'Home Food', 'type' => 'Home Food'],
            ],

            'sections' => [
                'today_deals' => $this->productsByType(clone $base, null, 12),

                'milk_subscription' => $this->productsByType(clone $base, 'Milk & Dairy', 100),
                'fresh_vegetables' => $this->productsByType(clone $base, 'Vegetables', 100),
                'fruits' => $this->productsByType(clone $base, 'Fruits', 100),
                'grocery_essentials' => $this->productsByType(clone $base, 'Groceries', 100),
                'pooja_essentials' => $this->productsByType(clone $base, 'Puja Samagri', 100),
                'flowers' => $this->productsByType(clone $base, 'Flowers', 100),

                // NEW CATEGORIES
                'bakery_snacks' => $this->productsByType(clone $base, 'Bakery & Snacks', 100),
                'beverages' => $this->productsByType(clone $base, 'Beverages', 100),
                'sweets_confectionery' => $this->productsByType(clone $base, 'Sweets & Confectionery', 100),
                'chaats_quick_snacks' => $this->productsByType(clone $base, 'Chaats & Quick Snacks', 100),
                'fish_seafood' => $this->productsByType(clone $base, 'Fish & Seafood', 100),
                'poultry' => $this->productsByType(clone $base, 'Poultry', 100),
                'meat' => $this->productsByType(clone $base, 'Meat', 100),
                'home_food' => $this->productsByType(clone $base, 'Home Food', 100),
            ],

            'cart' => [
                'count' => 0,
                'total' => 0,
            ],
        ]);
    }

    private function productsByType($query, ?string $productType, int $limit)
    {
        if ($productType) {
            $query->where('p.product_type', $productType);
        }

        return $query
            ->orderBy('p.title')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $price = (float) ($row->price ?? 0);
                $mrp = (float) ($row->compare_at_price ?? 0);

                return [
                    'product_id' => $row->product_id,
                    'variant_id' => $row->variant_id,

                    'shopify_product_gid' =>
                    'gid://shopify/Product/' . $row->product_id,

                    'shopify_variant_gid' =>
                    'gid://shopify/ProductVariant/' . $row->variant_id,
                    'name' => $row->title,
                    'variant' => $row->variant_title,
                    'category' => $row->product_type,
                    'sub_category' => $row->product_sub_type,
                    'image_url' => $row->img_src,
                    'price' => $price,
                    'mrp' => $mrp > 0 ? $mrp : null,
                    'discount_percent' => ($mrp > $price && $price > 0)
                        ? round((($mrp - $price) / $mrp) * 100)
                        : 0,
                    'stock' => (int) ($row->inventory_quantity ?? 0),
                    'unit' => $row->variant_title,
                ];
            });
    }
}
