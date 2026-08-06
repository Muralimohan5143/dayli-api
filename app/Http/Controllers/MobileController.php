<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use App\Models\Service;
use App\Models\ServiceVariant;
use App\Models\ProviderService;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestResponse;
use App\Models\ServiceRequestEvent;
use App\Models\ServiceRequestAssignment;

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
        $request->validate([
            'role' => [
                'nullable',
                'string',
                Rule::in([
                    'vendor',
                    'workman',
                ]),
            ],
        ]);

        $role = $request->query('role');

        $query = DB::table('services')
            ->select(
                'service_id as id',
                'service_id',
                'title as name',
                'title',
                'service_type',
                'handle',
                'description',
                'category',
                'img_src',
                'meta'
            )
            ->where('is_active', 1);

        if ($role === 'vendor') {
            $query->whereIn('service_type', [
                'vendor',
                'common',
            ]);
        }

        if ($role === 'workman') {
            $query->whereIn('service_type', [
                'workman',
                'common',
            ]);
        }

        $serviceTypes = $query
            ->orderBy('title')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $serviceTypes,
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


    public function getSubscriptionTypes(Request $request)
    {
        $types = DB::table('subscription_types')
            ->select('id', 'name', 'slug')
            ->where('status', 'active')
            ->get();

        return response()->json([
            'status' => true,
            'data'   => $types,
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

    public function mobileServiceCategories(Request $request)
    {
        $categories = Service::query()
            ->where('is_active', 1)
            ->whereNotNull('category')
            ->pluck('category')
            ->map(function ($category) {
                if (in_array($category, [
                    'Home Improvement',
                    'Housekeeping',
                    'Household',
                ])) {
                    return 'Household';
                }

                return $category;
            })
            ->unique()
            ->sort()
            ->values();

        return response()->json([
            'status' => true,
            'data' => $categories,
        ]);
    }

    public function mobileServicesByCategory(Request $request)
    {
        $request->validate([
            'category' => 'nullable|string',
            'q' => 'nullable|string',
        ]);

        $query = Service::query()
            ->with('variants')
            ->where('is_active', 1);

        if ($request->filled('category')) {

            if ($request->category === 'Household') {

                $query->whereIn('category', [
                    'Home Improvement',
                    'Housekeeping',
                    'Household',
                ]);
            } else {

                $query->where('category', $request->category);
            }
        }

        if ($request->filled('q')) {
            $search = $request->q;

            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $services = $query
            ->orderBy('title')
            ->get()
            ->map(function ($service) {
                return [
                    'id' => $service->service_id,
                    'service_id' => $service->service_id,
                    'title' => $service->title,
                    'name' => $service->title,
                    'description' => $service->description,
                    'category' => $service->category,
                    'handle' => $service->handle,
                    'img_src' => $service->img_src,
                    'variants' => $service->variants->map(function ($variant) {
                        return [
                            'variant_id' => $variant->variant_id,
                            'title' => $variant->title,
                            'price' => $variant->price,
                            'duration_minutes' => $variant->duration_minutes,
                        ];
                    }),
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $services,
        ]);
    }

    public function mobileServiceVariants(Request $request, $serviceId)
    {
        $variants = ServiceVariant::query()
            ->where('service_id', $serviceId)
            ->orderBy('price')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $variants,
        ]);
    }

    // public function mobileServiceProviders(Request $request, $serviceId)
    // {
    //     $providers = ProviderService::query()
    //         ->with(['service'])
    //         ->where('service_id', $serviceId)
    //         ->where('is_active', 1)
    //         ->orderByDesc('id')
    //         ->get()
    //         ->map(function ($item) {

    //             return [
    //                 'provider_service_id' => $item->id,
    //                 'provider_id' => $item->provider_id,
    //                 'provider_name' => 'Provider #' . $item->provider_id,
    //                 'description' => $item->description,
    //                 'starting_price' => $item->starting_price,
    //                 'rating' => '4.8',
    //                 'distance' => 'Nearby'
    //             ];
    //         });

    //     return response()->json([
    //         'status' => true,
    //         'data' => $providers
    //     ]);
    // }

    public function getMyProviderServices(Request $request)
    {
        $user = $request->user();

        $services = ProviderService::query()
            ->with(['service', 'variant'])
            ->where('provider_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'provider_id' => $item->provider_id,
                    'service_id' => $item->service_id,
                    'variant_id' => $item->variant_id,
                    'title' => optional($item->service)->title,
                    'subtitle' => $item->description ?: optional($item->service)->description,
                    'category' => optional($item->service)->category,
                    'variant_title' => optional($item->variant)->title,
                    'starting_price' => $item->starting_price ?: optional($item->variant)->price,
                    'status' => $item->is_active ? 'Active' : 'Inactive',
                    'is_active' => $item->is_active,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $services,
        ]);
    }

    public function addMyProviderService(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'service_id' => 'required|integer|exists:services,service_id',
            'variant_id' => 'nullable|integer|exists:service_variants,variant_id',
            'description' => 'nullable|string|max:1000',
            'starting_price' => 'nullable|numeric|min:0',
        ]);

        if ($request->filled('variant_id')) {
            $variant = ServiceVariant::where('variant_id', $request->variant_id)
                ->where('service_id', $request->service_id)
                ->first();

            if (!$variant) {
                return response()->json([
                    'status' => false,
                    'message' => 'Variant does not belong to selected service.',
                ], 422);
            }
        }

        $providerService = ProviderService::updateOrCreate(
            [
                'provider_id' => $user->id,
                'service_id' => $request->service_id,
                'variant_id' => $request->variant_id,
            ],
            [
                'description' => $request->description,
                'starting_price' => $request->starting_price,
                'is_active' => true,
            ]
        );

        return response()->json([
            'status' => true,
            'message' => 'Service added successfully.',
            'data' => $providerService,
        ]);
    }

    public function createMobileServiceRequest(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'service_id' => 'required|integer|exists:services,service_id',
            'title' => 'nullable|string|max:255',
            'ai_summary' => 'nullable|string',
            'request_json' => 'nullable|array',
            'attachments_json' => 'nullable|array',
            'preferred_date' => 'nullable|date',
            'preferred_time' => 'nullable|string',
            'preferred_time_from' => 'nullable|string',
            'preferred_time_to' => 'nullable|string',
            'address' => 'nullable|string',
            'nagar' => 'nullable|string|max:255',
            'zone_id' => 'nullable|integer',
        ]);

        $timeRange = $this->extractTimeRange(
            $request->preferred_time,
            $request->preferred_time_from,
            $request->preferred_time_to
        );

        $row = DB::transaction(function () use ($request, $user, $timeRange) {
            $serviceRequest = ServiceRequest::create([
                'customer_id' => $user->id,
                'service_id' => $request->service_id,
                'zone_id' => $request->zone_id,
                'title' => $request->title,
                'ai_summary' => $request->ai_summary,
                'request_json' => $request->request_json,
                'attachments_json' => $request->attachments_json,
                'preferred_date' => $request->preferred_date,
                'preferred_time_from' => $timeRange['from'],
                'preferred_time_to' => $timeRange['to'],
                'address' => $request->address,
                'nagar' => $request->nagar,
                'status' => 'posted',
            ]);

            ServiceRequestEvent::create([
                'service_request_id' => $serviceRequest->id,
                'actor_id' => $user->id,
                'actor_type' => 'customer',
                'event_type' => 'request_posted',
                'event_json' => [
                    'service_id' => $request->service_id,
                    'title' => $request->title,
                ],
                'notes' => 'Customer posted service requirement',
            ]);

            return $serviceRequest;
        });

        return response()->json([
            'status' => true,
            'message' => 'Service request posted successfully.',
            'data' => $row,
        ]);
    }

    public function getMyProviderServiceRequests(Request $request)
    {
        $user = $request->user();

        $providerServiceIds = ProviderService::query()
            ->where('provider_id', $user->id)
            ->where('is_active', 1)
            ->pluck('service_id')
            ->unique()
            ->values();

        $requests = ServiceRequest::query()
            ->with('service')
            ->whereIn('service_id', $providerServiceIds)
            ->whereIn('status', ['posted', 'customer_review'])
            ->orderByDesc('id')
            ->get()
            ->map(function ($item) use ($user) {
                $alreadyResponded = ServiceRequestResponse::where('service_request_id', $item->id)
                    ->where('provider_id', $user->id)
                    ->exists();

                return [
                    'id' => $item->id,
                    'customer_id' => $item->customer_id,
                    'service_id' => $item->service_id,
                    'title' => $item->title ?: optional($item->service)->title,
                    'service_title' => optional($item->service)->title,
                    'ai_summary' => $item->ai_summary,
                    'request_json' => $item->request_json,
                    'preferred_date' => $item->preferred_date,
                    'preferred_time_from' => $item->preferred_time_from,
                    'preferred_time_to' => $item->preferred_time_to,
                    'address' => $item->address,
                    'nagar' => $item->nagar,
                    'status' => $item->status,
                    'already_responded' => $alreadyResponded,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $requests,
        ]);
    }

    public function submitServiceRequestResponse(Request $request, $requestId)
    {
        $user = $request->user();

        $request->validate([
            'provider_service_id' => 'nullable|integer|exists:provider_services,id',
            'response_json' => 'nullable|array',
            'message' => 'nullable|string',
            'quoted_price' => 'nullable|numeric|min:0',
            'proposed_date' => 'nullable|date',
            'proposed_time' => 'nullable|string',
            'proposed_time_from' => 'nullable|string',
            'proposed_time_to' => 'nullable|string',
        ]);

        $serviceRequest = ServiceRequest::findOrFail($requestId);

        $providerService = ProviderService::query()
            ->where('provider_id', $user->id)
            ->where('service_id', $serviceRequest->service_id)
            ->where('is_active', 1)
            ->when($request->provider_service_id, function ($q) use ($request) {
                $q->where('id', $request->provider_service_id);
            })
            ->first();

        if (!$providerService) {
            return response()->json([
                'status' => false,
                'message' => 'You are not qualified for this service request.',
            ], 422);
        }

        $timeRange = $this->extractTimeRange(
            $request->proposed_time,
            $request->proposed_time_from,
            $request->proposed_time_to
        );

        $response = DB::transaction(function () use ($request, $user, $serviceRequest, $providerService, $timeRange) {
            $response = ServiceRequestResponse::updateOrCreate(
                [
                    'service_request_id' => $serviceRequest->id,
                    'provider_id' => $user->id,
                ],
                [
                    'provider_service_id' => $providerService->id,
                    'response_json' => $request->response_json,
                    'message' => $request->message,
                    'quoted_price' => $request->quoted_price,
                    'proposed_date' => $request->proposed_date,
                    'proposed_time_from' => $timeRange['from'],
                    'proposed_time_to' => $timeRange['to'],
                    'status' => 'submitted',
                ]
            );

            $serviceRequest->status = 'customer_review';
            $serviceRequest->save();

            ServiceRequestEvent::create([
                'service_request_id' => $serviceRequest->id,
                'actor_id' => $user->id,
                'actor_type' => 'provider',
                'event_type' => 'response_received',
                'event_json' => [
                    'response_id' => $response->id,
                    'quoted_price' => $request->quoted_price,
                ],
                'notes' => 'Provider submitted response',
            ]);

            return $response;
        });

        return response()->json([
            'status' => true,
            'message' => 'Response submitted successfully.',
            'data' => $response,
        ]);
    }

    public function getServiceRequestFeed(Request $request, $requestId)
    {
        $user = $request->user();

        $serviceRequest = ServiceRequest::query()
            ->with('service')
            ->where('id', $requestId)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $responses = ServiceRequestResponse::query()
            ->with('providerService.service')
            ->where('service_request_id', $serviceRequest->id)
            ->orderBy('quoted_price')
            ->orderBy('proposed_date')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'provider_id' => $item->provider_id,
                    'provider_name' => 'Provider #' . $item->provider_id,
                    'provider_service_id' => $item->provider_service_id,
                    'response_json' => $item->response_json,
                    'message' => $item->message,
                    'quoted_price' => $item->quoted_price,
                    'proposed_date' => $item->proposed_date,
                    'proposed_time_from' => $item->proposed_time_from,
                    'proposed_time_to' => $item->proposed_time_to,
                    'status' => $item->status,
                ];
            });

        $events = ServiceRequestEvent::query()
            ->where('service_request_id', $serviceRequest->id)
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => true,
            'data' => [
                'request' => $serviceRequest,
                'responses' => $responses,
                'events' => $events,
            ],
        ]);
    }

    public function selectServiceRequestProviders(Request $request, $requestId)
    {
        $user = $request->user();

        $request->validate([
            'primary_response_id' => 'required|integer|exists:service_request_responses,id',
            'secondary_response_id' => 'nullable|integer|exists:service_request_responses,id',
        ]);

        $serviceRequest = ServiceRequest::query()
            ->where('id', $requestId)
            ->where('customer_id', $user->id)
            ->firstOrFail();

        $primary = ServiceRequestResponse::where('id', $request->primary_response_id)
            ->where('service_request_id', $serviceRequest->id)
            ->firstOrFail();

        $secondary = null;

        if ($request->secondary_response_id) {
            $secondary = ServiceRequestResponse::where('id', $request->secondary_response_id)
                ->where('service_request_id', $serviceRequest->id)
                ->firstOrFail();
        }

        DB::transaction(function () use ($serviceRequest, $primary, $secondary, $user) {
            ServiceRequestResponse::where('service_request_id', $serviceRequest->id)
                ->update(['status' => 'backup_queued']);

            $primary->status = 'primary_selected';
            $primary->save();

            if ($secondary) {
                $secondary->status = 'secondary_selected';
                $secondary->save();
            }

            $serviceRequest->primary_provider_id = $primary->provider_id;
            $serviceRequest->secondary_provider_id = $secondary?->provider_id;
            $serviceRequest->current_provider_id = $primary->provider_id;
            $serviceRequest->assignment_attempts = $serviceRequest->assignment_attempts + 1;
            $serviceRequest->last_assignment_at = now();
            $serviceRequest->status = 'assigned';
            $serviceRequest->save();

            ServiceRequestAssignment::create([
                'service_request_id' => $serviceRequest->id,
                'provider_id' => $primary->provider_id,
                'provider_service_id' => $primary->provider_service_id,
                'service_request_response_id' => $primary->id,
                'priority_order' => 1,
                'assignment_type' => 'primary',
                'status' => 'assigned',
                'assigned_at' => now(),
            ]);

            if ($secondary) {
                ServiceRequestAssignment::create([
                    'service_request_id' => $serviceRequest->id,
                    'provider_id' => $secondary->provider_id,
                    'provider_service_id' => $secondary->provider_service_id,
                    'service_request_response_id' => $secondary->id,
                    'priority_order' => 2,
                    'assignment_type' => 'secondary',
                    'status' => 'assigned',
                    'assigned_at' => now(),
                ]);
            }

            ServiceRequestEvent::create([
                'service_request_id' => $serviceRequest->id,
                'actor_id' => $user->id,
                'actor_type' => 'customer',
                'event_type' => 'providers_selected',
                'event_json' => [
                    'primary_provider_id' => $primary->provider_id,
                    'secondary_provider_id' => $secondary?->provider_id,
                ],
                'notes' => 'Customer selected primary and secondary provider',
            ]);
        });

        return response()->json([
            'status' => true,
            'message' => 'Providers selected successfully.',
        ]);
    }
    private function extractTimeRange($slot = null, $from = null, $to = null): array
    {
        if ($from || $to) {
            return [
                'from' => $this->normalizeTime($from),
                'to' => $this->normalizeTime($to),
            ];
        }

        if (!$slot) {
            return [
                'from' => null,
                'to' => null,
            ];
        }

        $parts = explode('-', $slot);

        return [
            'from' => $this->normalizeTime(trim($parts[0] ?? '')),
            'to' => $this->normalizeTime(trim($parts[1] ?? '')),
        ];
    }

    private function normalizeTime($time)
    {
        if (!$time) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($time)->format('H:i:s');
        } catch (\Exception $e) {
            return null;
        }
    }

    public function getCustomerServiceRequests(Request $request)
    {
        $user = $request->user();

        $requests = ServiceRequest::query()
            ->with('service')
            ->where('customer_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(function ($item) {

                $responseCount = ServiceRequestResponse::where(
                    'service_request_id',
                    $item->id
                )->count();

                return [
                    'id' => $item->id,
                    'title' => $item->ai_summary,
                    'service_title' => optional($item->service)->title,
                    'address' => $item->address,
                    'preferred_date' => $item->preferred_date,
                    'status' => $item->status,
                    'response_count' => $responseCount,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $requests
        ]);
    }

    public function getMyServiceAssignments(Request $request)
    {
        $user = $request->user();

        $assignments = ServiceRequestAssignment::query()
            ->with(['serviceRequest.service', 'response'])
            ->where('provider_id', $user->id)
            ->whereIn('status', [
                'assigned',
                'provider_confirmed',
                'provider_enroute',
                'in_progress',
                'completed'
            ])
            ->orderByDesc('id')
            ->get()
            ->map(function ($item) {
                $req = $item->serviceRequest;

                return [
                    'id' => $item->id,
                    'service_request_id' => $item->service_request_id,
                    'provider_id' => $item->provider_id,
                    'service_title' => optional(optional($req)->service)->title,
                    'title' => optional($req)->ai_summary ?: optional($req)->title,
                    'address' => optional($req)->address,
                    'preferred_date' => optional($req)->preferred_date,
                    'preferred_time_from' => optional($req)->preferred_time_from,
                    'preferred_time_to' => optional($req)->preferred_time_to,
                    'quoted_price' => optional($item->response)->quoted_price,
                    'customer_id' => optional($req)->customer_id,
                    'assignment_type' => $item->assignment_type,
                    'status' => $item->status,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $assignments,
        ]);
    }

    public function updateServiceAssignmentStatus(Request $request, $assignmentId)
    {
        $user = $request->user();

        $request->validate([
            'status' => 'required|string|in:provider_confirmed,provider_enroute,in_progress,completed,declined,no_show,cancelled',
        ]);

        $assignment = ServiceRequestAssignment::where('id', $assignmentId)
            ->where('provider_id', $user->id)
            ->firstOrFail();

        $assignment->status = $request->status;

        if ($request->status === 'provider_confirmed') {
            $assignment->accepted_at = now();
        } elseif ($request->status === 'provider_enroute') {
            $assignment->enroute_at = now();
        } elseif ($request->status === 'in_progress') {
            $assignment->started_at = now();
        } elseif ($request->status === 'completed') {
            $assignment->completed_at = now();
        }

        $assignment->save();

        $serviceRequest = ServiceRequest::find($assignment->service_request_id);

        if ($serviceRequest) {
            $serviceRequest->status = $request->status;
            $serviceRequest->save();

            ServiceRequestEvent::create([
                'service_request_id' => $serviceRequest->id,
                'actor_id' => $user->id,
                'actor_type' => 'provider',
                'event_type' => $request->status,
                'event_json' => [
                    'assignment_id' => $assignment->id,
                    'provider_id' => $user->id,
                ],
                'notes' => 'Provider updated job status',
            ]);
        }

        return response()->json([
            'status' => true,
            'message' => 'Status updated successfully.',
            'data' => $assignment,
        ]);
    }
}
