<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MobileController extends Controller
{
    // Fetch subscription types
    public function getSubscriptions(Request $request)
    {
        $subscriptions = DB::table('subscription_types')
            ->select('id', 'name')
            ->where('status', 'active')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $subscriptions,
        ]);
    }

    // Fetch service types
    public function getServiceTypes(Request $request)
    {
        $serviceTypes = DB::table('services')
            ->select('service_id as id', 'title as name')
            ->where('is_active', '1')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $serviceTypes,
        ]);
    }

    /**
     * Fetch subscription sub-types based on subscription type id
     * Previously: hard-coded $map
     * Now: driven directly from subscription_sub_types table
     */
    public function getSubscriptionSubTypes($id)
    {
        $subTypes = DB::table('subscription_sub_types')
            ->select('id', 'name')
            ->where('subscription_type_id', $id)   // 🔁 change to 'type_id' if that's your FK name
            ->where('status', 'active')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $subTypes,
        ]);
    }

    /**
     * Fetch products (only id and title) for a given subscription_sub_type id
     * Refactored to use product_type + product_sub_type like zonevariantslist.php
     */
    public function productsBySubType(Request $request, $subTypeId)
    {
        // 1) Load the subtype record
        $sub = DB::table('subscription_sub_types')
            ->where('id', $subTypeId)
            ->where('status', 'active')
            ->first();

        if (!$sub) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid subscription sub-type',
            ], 404);
        }

        // 2) Load the parent subscription type
        $type = DB::table('subscription_types')
            ->where('id', $sub->subscription_type_id) // change if your FK is different
            ->where('status', 'active')
            ->first();

        if (!$type) {
            return response()->json([
                'status'  => false,
                'message' => 'Invalid subscription type for this sub-type',
            ], 404);
        }

        // Normalize keys and names
        $typeKey      = $type->slug ?? $this->normalizeKey($type->name);
        $subTypeKey   = $sub->slug  ?? $this->normalizeKey($sub->name);
        $typeNameLc   = mb_strtolower($type->name);
        $subNameLc    = mb_strtolower($sub->name);
        $typeKeyLc    = mb_strtolower($typeKey);
        $subTypeKeyLc = mb_strtolower($subTypeKey);

        $search = $request->query('q'); // optional

        // 3) Query PRODUCTS table directly (no variants), with lenient matching
        $productsQuery = DB::table('products')
            ->select('product_id', 'title')
            ->where('status', 'ACTIVE')
            ->where(function ($q) use ($typeKeyLc, $typeNameLc) {
                // Match product_type by slug OR name (case-insensitive)
                $q->whereRaw('LOWER(COALESCE(product_type, "")) = ?', [$typeKeyLc])
                    ->orWhereRaw('LOWER(COALESCE(product_type, "")) = ?', [$typeNameLc]);
            })
            ->where(function ($q) use ($subTypeKeyLc, $subNameLc) {
                // Match product_sub_type by slug OR name (case-insensitive)
                $q->whereRaw('LOWER(COALESCE(product_sub_type, "")) = ?', [$subTypeKeyLc])
                    ->orWhereRaw('LOWER(COALESCE(product_sub_type, "")) = ?', [$subNameLc]);
            });

        if (!empty($search)) {
            $searchLc = '%' . mb_strtolower($search) . '%';
            $productsQuery->whereRaw('LOWER(title) LIKE ?', [$searchLc]);
        }

        $products = $productsQuery->get();

        return response()->json([
            'status' => true,
            'data'   => $products,
            // if you want to debug once, uncomment:
            // 'debug'  => [
            //     'type_key'      => $typeKey,
            //     'subtype_key'   => $subTypeKey,
            //     'count'         => $products->count(),
            // ],
        ]);
    }
    /**
     * Product variants, now with optional zone-aware price (similar to zonevariantslist)
     */
    public function productVariants(Request $request, $productId)
    {
        $zoneId = $request->query('zone_id');   // optional

        $query = DB::table('variants as v')
            ->join('products as p', 'v.product_id', '=', 'p.product_id');

        if ($zoneId) {
            $query->leftJoin('zone_product_variants as zpv', function ($j) use ($zoneId) {
                $j->on('zpv.product_id', '=', 'v.product_id')
                    ->on('zpv.variant_id', '=', 'v.variant_id')
                    ->where('zpv.zone_id', '=', $zoneId);
            });
        }

        $query->where('v.product_id', $productId);

        // zone price fallback logic
        $mrpExpr = 'v.price';
        if ($zoneId && Schema::hasColumn('zone_product_variants', 'customer_price')) {
            $mrpExpr = 'COALESCE(zpv.customer_price, v.price)';
        } elseif ($zoneId && Schema::hasColumn('zone_product_variants', 'price')) {
            $mrpExpr = 'COALESCE(zpv.price, v.price)';
        }

        $variants = $query
            ->select(
                'p.product_id',
                'p.title as product_name',
                'v.variant_id',
                'v.title as variant_title',
                DB::raw($mrpExpr . ' AS variant_price')
            )
            ->orderBy('v.position')
            ->get();

        if ($variants->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'No variants found for this product',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data'   => $variants,
        ]);
    }

    public function checkPincode($pincode)
    {
        $zone = DB::table('zone_pincodes')->where('pin_code', $pincode)->first();

        if ($zone) {
            return response()->json([
                'status'  => true,
                'message' => 'Service available',
                'zone_id' => $zone->zone_id,
            ]);
        } else {
            return response()->json([
                'status'  => false,
                'message' => 'Our service is not available in this area.',
            ]);
        }
    }

    public function checkLocation(Request $request)
    {
        $lat = $request->query('lat');
        $lon = $request->query('lon');

        if (!$lat || !$lon) {
            return response()->json([
                'status'  => false,
                'message' => 'Latitude and longitude are required.',
            ]);
        }

        // ✅ Fetch only active allowed zones from DB (no model needed)
        $zones = DB::table('zones')
            ->whereIn('code', ['zone_kurnool_checkpost', 'zone_kupari_checkpost'])
            ->where('status', 'active')
            ->select('name', 'focal_lat', 'focal_lon')
            ->get();

        if ($zones->isEmpty()) {
            return response()->json([
                'status'  => false,
                'message' => 'No active service zones found.',
            ]);
        }

        $radius = 5; // km — allowed distance

        foreach ($zones as $zone) {
            $distance = $this->calculateDistance($lat, $lon, $zone->focal_lat, $zone->focal_lon);

            if ($distance <= $radius) {
                return response()->json([
                    'status'  => true,
                    'message' => '✅ You are within the service area: ' . $zone->name,
                ]);
            }
        }

        return response()->json([
            'status'  => false,
            'message' => '🚫 Our service is not available in this area.',
        ]);
    }

    // ✅ Helper: normalize strings to "snake" keys (for slugs, etc.)
    private function normalizeKey(string $value): string
    {
        $v = trim(mb_strtolower($value));
        $v = preg_replace('/[^a-z0-9]+/u', '_', $v);
        return trim($v, '_');
    }

    /**
     * Shared base query (similar to zonevariantslist.php::baseQuery)
     * Filters by product_type + product_sub_type and optional zone / search
     */
    private function baseVariantQuery(
        string $productType,
        string $productSubType,
        ?int $zoneId = null,
        ?string $search = null,
        bool $onlyActive = true
    ) {
        $typeRaw = trim($productType);
        $subRaw  = trim($productSubType);

        if ($typeRaw === '' || $subRaw === '') {
            return DB::table('variants as v')->whereRaw('1 = 0');
        }

        $typeLc = mb_strtolower($typeRaw);
        $subLc  = mb_strtolower($subRaw);
        $subKey = $this->normalizeKey($subRaw);

        $query = DB::table('variants as v')
            ->join('products as p', 'p.product_id', '=', 'v.product_id');

        if ($zoneId) {
            $query->leftJoin('zone_product_variants as zpv', function ($j) use ($zoneId) {
                $j->on('zpv.product_id', '=', 'v.product_id')
                    ->on('zpv.variant_id', '=', 'v.variant_id')
                    ->where('zpv.zone_id', '=', $zoneId);
            });
        }

        $query->whereRaw('LOWER(COALESCE(p.product_type, "")) = ?', [$typeLc])
            ->where(function ($w) use ($subLc, $subKey) {
                $w->whereRaw('LOWER(COALESCE(p.product_sub_type, "")) = ?', [$subKey])
                    ->orWhereRaw('LOWER(COALESCE(p.product_sub_type, "")) = ?', [$subLc]);
            });

        if (!empty($search)) {
            $q = '%' . mb_strtolower($search) . '%';
            $query->whereRaw('LOWER(p.title) LIKE ?', [$q]);
        }

        if ($onlyActive && $zoneId) {
            $query->where('zpv.is_active', 1);
        }

        // Prefer zone price when available, else v.price
        $mrpExpr = 'v.price';
        if ($zoneId && Schema::hasColumn('zone_product_variants', 'customer_price')) {
            $mrpExpr = 'COALESCE(zpv.customer_price, v.price)';
        } elseif ($zoneId && Schema::hasColumn('zone_product_variants', 'price')) {
            $mrpExpr = 'COALESCE(zpv.price, v.price)';
        }

        return $query->select([
            'p.product_type',
            'p.product_sub_type',
            'p.product_id',
            'p.title as product_title',
            'v.variant_id',
            'v.title as variant_title',
            'v.sku',
            DB::raw($mrpExpr . ' AS mrp_value'),
            DB::raw('COALESCE(zpv.is_active, 0) as in_zone'),
        ])->orderBy('p.title')->orderBy('v.position');
    }

    // ✅ Helper function to calculate distance
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
