<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobileCategoryController extends Controller
{
    public function index(Request $request)
    {
        $zoneId = (int) $request->query('zone_id', 1);
        $category = $request->query('category');

        if (!$category) {
            return response()->json([
                'success' => false,
                'message' => 'category is required',
            ], 422);
        }

        $products = DB::table('zone_product_variants as zpv')
            ->join('products as p', 'p.product_id', '=', 'zpv.product_id')
            ->leftJoin('variants as v', 'v.variant_id', '=', 'zpv.variant_id')
            ->where('zpv.zone_id', $zoneId)
            ->where('zpv.is_active', 1)
            ->whereNull('zpv.deleted_at')
            ->where('p.product_type', $category)
            ->whereNotNull('p.img_src')
            ->where('p.img_src', '!=', '')
            ->select([
                'p.product_id',
                'p.title',
                'p.product_type',
                'p.product_sub_type',
                'p.img_src',
                'v.variant_id',
                'v.title as variant_title',
                'v.price',
                'v.compare_at_price',
                'v.inventory_quantity',
            ])
            ->orderBy('p.title')
            ->limit(100)
            ->get()
            ->map(function ($row) {
                $price = (float) ($row->price ?? 0);
                $mrp = (float) ($row->compare_at_price ?? 0);

                return [
                    'product_id' => $row->product_id,
                    'variant_id' => $row->variant_id,
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

        return response()->json([
            'success' => true,
            'zone_id' => $zoneId,
            'category' => $category,
            'products' => $products,
        ]);
    }
}
