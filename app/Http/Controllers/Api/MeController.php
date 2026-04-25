<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\DraftOrderItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendPushToUserJob;
use Illuminate\Support\Facades\Log;


class MeController extends Controller
{
    /**
     * GET /api/me
     * - returns role + zone_id
     * - sync zone_id into sub_change_requests (fill where null)
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated',
                'role' => null,
                'roles' => [],
            ], 401);
        }

        // ✅ SYNC: fill scr.zone_id from customer users.zone_id (only where missing)
        DB::table('sub_change_requests as scr')
            ->join('users as u', 'u.id', '=', 'scr.for_user_id')
            ->join('model_has_roles as mhr', function ($join) {
                $join->on('mhr.model_id', '=', 'u.id')
                    ->where('mhr.model_type', \App\Models\User::class);
            })
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('r.name', 'customer')      // ✅ only customers
            ->whereNull('scr.zone_id')
            ->whereNotNull('u.zone_id')
            ->update(['scr.zone_id' => DB::raw('u.zone_id')]);

        // ✅ Role slug from Spatie (normalized for app)
        $roleSlug = null;
        $roles = [];

        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames()->values()->toArray();

            if (in_array('vendor', $roles, true) || in_array('vendor-milk', $roles, true)) {
                $roleSlug = 'vendor';
            } elseif (in_array('workman-delivery-boy', $roles, true) || in_array('workman', $roles, true)) {
                $roleSlug = 'workman';
            } elseif (in_array('customer', $roles, true)) {
                $roleSlug = 'customer';
            } else {
                $roleSlug = $roles[0] ?? null;
            }
        }


        $userServices = DB::table('user_services')
            ->where('user_id', $user->id)
            ->select(
                'id',
                'role_name',
                'service_handle',
                'subscription_type_id',
                'zone_id',
                'status',
                'approved_by',
                'approved_at'
            )
            ->orderByDesc('id')
            ->get();
        return response()->json([
            'id'      => $user->id,
            'name'    => $user->name ?? null,
            'zone_id' => $user->zone_id ?? null,
            'role'    => $roleSlug,
            'roles'   => $roles,
            'user_services' => $userServices,
        ]);
    }


    private function approvedDeliveryServiceForUser(int $userId, int $subscriptionTypeId)
    {
        return DB::table('user_services')
            ->where('user_id', $userId)
            ->where('role_name', 'workman')
            ->where('service_handle', 'workman-delivery-boy')
            ->where('subscription_type_id', $subscriptionTypeId)
            ->where('status', 'approved')
            ->orderByDesc('id')
            ->first();
    }

    private function activeDeliveryTaskForUserAndType(int $userId, int $subscriptionTypeId)
    {
        return DB::table('delivery_tasks')
            ->where('delivery_exec_id', $userId)
            ->where('subscription_type_id', $subscriptionTypeId)
            ->whereIn('status', ['today', 'in_progress'])
            ->orderByDesc('id')
            ->first();
    }

    private function tryCreateAgentDeliveredEvent(int $zoneId, int $subscriptionTypeId, string $deliveryDate): void
    {
        $deliveryDate = Carbon::parse($deliveryDate)->toDateString();
        if ($zoneId <= 0 || $subscriptionTypeId <= 0 || !$deliveryDate) {
            return;
        }

        $base = DB::table('orders as o')
            ->join('draft_orders as d', 'd.id', '=', 'o.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'd.change_request_id')
            ->where('o.zone_id', $zoneId)
            ->whereDate('o.delivery_date', $deliveryDate)
            ->whereNotIn('o.status', ['cancelled'])
            ->where('scr.party_type', 'consumer')
            ->where('scr.subscription_type_id', $subscriptionTypeId);

        $total = (clone $base)->count();

        $delivered = (clone $base)
            ->where('o.delivery_status', 'delivered')
            ->count();

        // ❌ if any pending → stop
        if ($total <= 0 || $total !== $delivered) {
            return;
        }

        // ✅ create ONLY ONE delivery event
        DB::table('outbox_events')->updateOrInsert(
            [
                'idempotency_key' => "agent_delivered_entered:zone:{$zoneId}:date:{$deliveryDate}:subtype:{$subscriptionTypeId}",
            ],
            [
                'event_type'     => 'agent_delivered_entered',
                'aggregate_type' => 'zone',
                'aggregate_id'   => $zoneId,
                'scheduled_at'   => now(),
                'payload'        => json_encode([
                    'zone_id' => $zoneId,
                    'delivery_date' => $deliveryDate,
                    'subscription_type_id' => $subscriptionTypeId,
                    'source' => 'dayli_app',
                ]),
                'status'        => 'pending',
                'attempts'      => 0,
                'max_attempts'  => 10,
                'updated_at'    => now(),
                'created_at'    => now(),
            ]
        );
    }
    // private function currentDeliveryTaskForUser(int $userId)
    // {
    //     return DB::table('delivery_tasks')
    //         ->where('delivery_exec_id', $userId)
    //         ->whereIn('status', ['today', 'in_progress'])
    //         ->orderByDesc('id')
    //         ->first();
    // }

    // private function resolveSubscriptionTypeIdFromTask(?string $deliveryTaskTitle): ?int
    // {
    //     if (!$deliveryTaskTitle) return null;

    //     $title = strtolower($deliveryTaskTitle);

    //     // ✅ map from delivery_tasks.subscription_type_id text → subscription_types.name
    //     // adjust keywords as you like
    //     $key = null;
    //     if (str_contains($title, 'milk')) $key = 'milk';
    //     else if (str_contains($title, 'veg')) $key = 'veg';
    //     else if (str_contains($title, 'vegetable')) $key = 'veg';
    //     else if (str_contains($title, 'grocery')) $key = 'grocery';

    //     if (!$key) return null;

    //     $row = DB::table('subscription_types')
    //         ->select('id', 'name')
    //         ->whereRaw('LOWER(name) LIKE ?', ["%{$key}%"])
    //         ->first();

    //     return $row ? (int)$row->id : null;
    // }


    /**
     * GET /api/my-work/orders?type=milk
     * - returns TODAY active items from draft_order_items for this delivery boy's zone
     */
    public function myWorkOrders(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $today = Carbon::today()->toDateString();

        // ✅ subscription type must come from request now
        $requestedSubTypeId = (int) $request->query('subscription_type_id');

        if ($requestedSubTypeId <= 0) {
            return response()->json([
                'message' => 'subscription_type_id is required',
                'data' => [],
            ], 422);
        }

        // ✅ 1) First check approval in user_services
        $approvedService = $this->approvedDeliveryServiceForUser(
            (int) $user->id,
            $requestedSubTypeId
        );

        if (!$approvedService) {
            return response()->json([
                'ok' => false,
                'message' => 'Your service account is not approved yet.',
                'required_role' => 'workman',
                'required_service_handle' => 'workman-delivery-boy',
                'subscription_type_id' => $requestedSubTypeId,
            ], 403);
        }

        // ✅ subscription type comes from approved user service
        $subTypeId = $requestedSubTypeId;

        // ✅ 2) delivery task is operational only, not permission
        $task = $this->activeDeliveryTaskForUserAndType((int) $user->id, $subTypeId);

        // ✅ zone priority: active task zone first, else approved service zone, else user zone
        $zoneId = (int) (
            $task->zone_id
            ?? $approvedService->zone_id
            ?? $user->zone_id
            ?? 0
        );

        if ($zoneId <= 0) {
            return response()->json([
                'message' => 'No zone assigned for this approved delivery service',
                'data' => [],
            ], 422);
        }

        // ✅ Mode switch for modal dropdowns (pending/done dates)
        $mode = (string) $request->query('mode', '');  // mode=dates

        // ✅ 3) SYNC scr.zone_id from delivery task zone_id (only where null)
        DB::table('sub_change_requests')
            ->whereNull('zone_id')
            ->where('subscription_type_id', $subTypeId)
            ->update(['zone_id' => $zoneId]);

        // if (!$subType) {
        //     return response()->json([
        //         'zone_id' => $zoneId,
        //         'date' => $today,
        //         'type' => $type,
        //         'data' => [],
        //     ]);
        // }


        $today = Carbon::today()->toDateString();

        // ✅ accept both keys from flutter
        $requestedDate = $request->query('delivery_date') ?? $request->query('deliveryDate');
        $targetDate = $requestedDate ? Carbon::parse($requestedDate)->toDateString() : $today;
        $baseRows = DB::table('draft_order_items as doi')
            ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
            ->join('users as u', 'u.id', '=', 'scr.for_user_id')

            // ✅ ONLY CUSTOMERS (role filter)
            ->join('model_has_roles as mhr', function ($join) {
                $join->on('mhr.model_id', '=', 'u.id')
                    ->where('mhr.model_type', \App\Models\User::class);
            })
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('r.name', 'customer')
            ->where('scr.party_type', 'consumer')   // ✅ ADD THIS

            ->where('scr.zone_id', $zoneId)
            ->where('scr.subscription_type_id', $subTypeId)
            ->where('do.status', 'active')
            ->where('doi.status', 'active')
            // ✅ IMPORTANT: only items active on targetDate
            ->where(function ($w) use ($targetDate) {
                $w->whereNull('doi.start_date')
                    ->orWhereDate('doi.start_date', '<=', $targetDate);
            })
            ->where(function ($w) use ($targetDate) {
                $w->whereNull('doi.end_date')
                    ->orWhereDate('doi.end_date', '>=', $targetDate);
            })
            ->orderBy('u.name')
            ->select([
                'doi.id as draft_order_item_id',
                'doi.draft_order_id',
                'scr.for_user_id as customer_id',
                DB::raw("COALESCE(NULLIF(u.name,''), NULLIF(u.display_name,''), NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))), ''), 'Customer') as customer_name"),
                DB::raw("COALESCE(u.phone_normalized, u.phone, '') as customer_phone"),
                DB::raw("COALESCE(u.nagar, '') as nagar"),
                DB::raw("COALESCE(u.address, '') as address"),
            ])
            ->get();

        // ✅ Fetch today's order status for these customers (single query, no N+1)
        $customerIds = $baseRows->pluck('customer_id')->filter()->unique()->values()->all();

        // ✅ Ensure daily order rows exist for targetDate (default: today)
        // This makes sure "today" shows in pending dates + UI status works.
        $requestedDate = $request->query('delivery_date') ?? $request->query('deliveryDate'); // ✅ accept both
        $targetDate = $requestedDate ? Carbon::parse($requestedDate)->toDateString() : $today;


        if (!empty($customerIds)) {

            $existingCustomerIds = Order::query()
                ->whereIn('customer_id', $customerIds)
                ->whereDate('delivery_date', $targetDate)
                ->pluck('customer_id')
                ->unique()
                ->all();

            $missingCustomerIds = array_values(array_diff($customerIds, $existingCustomerIds));

            if (!empty($missingCustomerIds)) {
                $now = Carbon::now();

                $insertRows = array_map(function ($cid) use ($zoneId, $subTypeId, $targetDate, $now) {
                    return [
                        'customer_id'          => (int) $cid,
                        'zone_id'              => (int) $zoneId,
                        // 'subscription_type_id' => (int) $subTypeId,
                        'delivery_date'        => $targetDate,
                        'delivery_status'      => 'pending',
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ];
                }, $missingCustomerIds);

                DB::table('orders')->insert($insertRows);
            }
        }


        // ✅ NEW: For modal dropdowns (NOT customer-wise)
        if ($mode === 'dates') {

            $pendingDates = DB::table('orders')
                ->where('zone_id', $zoneId)
                ->whereIn('customer_id', $customerIds)   // ✅ ADD THIS
                ->whereNotNull('delivery_date')
                ->where('delivery_status', 'pending')
                ->whereDate('delivery_date', '<=', $today)
                ->selectRaw("DATE(delivery_date) as d")
                ->groupBy('d')
                ->orderByDesc('d')
                ->pluck('d')
                ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
                ->values();

            $doneDates = DB::table('orders')
                ->whereIn('customer_id', $customerIds)   // ✅ ADD THIS
                ->whereNotNull('delivery_date')
                ->where('delivery_status', 'delivered')
                ->where(function ($q) use ($zoneId) {
                    $q->where('zone_id', $zoneId)->orWhereNull('zone_id');
                })
                ->selectRaw("DATE(delivery_date) as d")
                ->groupBy('d')
                ->orderByDesc('d')
                ->pluck('d')
                ->map(fn($d) => Carbon::parse($d)->format('Y-m-d'))
                ->values();


            return response()->json([
                'delivery_task_id' => $task ? (int) $task->id : 0,
                'subscription_type_id' => (int) $subTypeId,
                'zone_id' => (int) $zoneId,
                'pending_dates' => $pendingDates,
                'done_dates' => $doneDates,
            ]);
        }




        $type = (string) $request->query('status', 'today'); // today|pending|completed
        // ✅ NEW: date filter for list UI
        $requestedDate = $request->query('delivery_date'); // '2026-02-10'
        $targetDate = $requestedDate ? Carbon::parse($requestedDate)->toDateString() : $today;



        $todayOrdersMap = collect();
        if (!empty($customerIds)) {
            $todayOrders = Order::query()
                ->select('id', 'customer_id', 'delivery_status')
                ->whereIn('customer_id', $customerIds)
                ->whereDate('delivery_date', $targetDate)
                ->orderByDesc('id')
                ->get();

            // keep latest order per customer
            $todayOrdersMap = $todayOrders->groupBy('customer_id')->map(function ($g) {
                return $g->first();
            });

            // ✅ Pull order_items for these orders (for the selected targetDate)
            $orderIds = $todayOrdersMap->filter()->pluck('id')->unique()->values()->all();

            $orderItemsMap = collect(); // order_id => (product_id|variant_id => order_item)
            if (!empty($orderIds)) {
                $orderItems = OrderItem::query()
                    ->select('order_id', 'product_id', 'variant_id', 'quantity')
                    ->whereIn('order_id', $orderIds)
                    ->get();

                $orderItemsMap = $orderItems
                    ->groupBy('order_id')
                    ->map(function ($items) {
                        return $items->keyBy(function ($oi) {
                            $pid = (int) ($oi->product_id ?? 0);
                            $vid = (int) ($oi->variant_id ?? 0); // null => 0
                            return $pid . '|' . $vid;
                        });
                    });
            }
        }

        // ✅ OLD PENDING DATES (for dropdown)
        $pendingByCustomer = collect();

        if (!empty($customerIds)) {
            $pendingRows = DB::table('orders')
                ->select('customer_id', 'delivery_date')
                ->whereIn('customer_id', $customerIds)
                ->where('delivery_status', 'pending')
                ->whereNull('delivered_at')
                ->whereNotNull('delivery_date')
                ->whereDate('delivery_date', '<=', $today)
                ->distinct()
                ->orderByDesc('delivery_date')
                ->get()
                ->groupBy('customer_id');

            $pendingByCustomer = $pendingRows->map(function ($rows) {
                return $rows->pluck('delivery_date')
                    ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
                    ->values()
                    ->all();
            });
        }


        $ids = $baseRows->pluck('draft_order_item_id')->all();
        $customerMap = $baseRows->keyBy('draft_order_item_id');

        $items = DraftOrderItem::query()
            ->with('product')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $rows = collect($ids)->map(function ($id) use ($items, $customerMap, $todayOrdersMap, $pendingByCustomer, $orderItemsMap) {



            $item = $items->get($id);
            $c    = $customerMap->get($id);

            if (!$item || !$c) return null;

            $customerId   = (int) ($c->customer_id ?? 0);
            $to           = $todayOrdersMap->get($customerId);
            $pendingDates = $pendingByCustomer[$customerId] ?? [];

            // ✅ Override qty with order_items if exists (even if 0)
            $oiQty = null;

            if ($to) {
                $key = ((int)$item->product_id) . '|' . ((int)$item->variant_id);
                $oi = data_get($orderItemsMap, $to->id . '.' . $key);
                if ($oi) {
                    $oiQty = (float) $oi->quantity;
                }
            }

            return [
                'draft_order_item_id' => (int) $item->id,
                'draft_order_id'      => (int) $item->draft_order_id,

                'customer_id'         => $customerId,
                'customer_name'       => $c->customer_name ?? 'Customer',
                'customer_phone'      => $c->customer_phone ?? '',
                'pending_dates'       => $pendingDates,

                'today_order_id'      => $to ? (int) $to->id : 0,
                'delivery_status'     => $to->delivery_status ?? 'pending',

                'nagar'               => $c->nagar ?? '',
                'address'             => $c->address ?? '',

                'product_title'       => optional($item->product)->title ?? 'Product',
                'image_url'           => optional($item->product)->img_src ?? '',

                'qty' => $oiQty !== null ? $oiQty : (float) $item->qty,
                'unit'                => $item->unit,
                'price_snapshot'      => $item->price_snapshot,
                'frequency_type'      => $item->frequency_type,
                'item_status'         => $item->status ?? 'active',

                'product_id'          => (int) $item->product_id,
                'variant_id'          => (int) $item->variant_id,
            ];
        })->filter()->values();

        // ✅ Filter list by requested tab type
        if ($type === 'pending') {
            $rows = $rows->filter(fn($r) => ($r['delivery_status'] ?? '') === 'pending')->values();
        } elseif ($type === 'completed') {
            $rows = $rows->filter(fn($r) => ($r['delivery_status'] ?? '') === 'delivered')->values();
        }



        $requestedTypeId = (int) $request->query('subscription_type_id', 0);
        if ($requestedTypeId > 0) {
            $subTypeId = $requestedTypeId;
        } else {
            $subTypeId = (int) ($task->subscription_type_id ?? 0);
        }

        Log::info('myWorkOrders final debug', [
            'targetDate' => $targetDate,
            'customerIds_count' => count($customerIds),
            'has_11316' => in_array(11316, $customerIds, true),
            'todayOrder_11316' => $todayOrders->firstWhere('customer_id', 11316),
        ]);

        return response()->json([
            'delivery_task_id' => $task ? (int) $task->id : 0,
            'task_subscription_type_id' => $task ? (int) $task->subscription_type_id : 0,
            'subscription_type_id' => (int) $subTypeId,
            'zone_id' => $zoneId,
            'date' => $targetDate,
            'data' => $rows,
        ]);
    }

    public function myWorkSubscriptionTypes(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $types = DB::table('user_services as us')
            ->join('subscription_types as st', 'st.id', '=', 'us.subscription_type_id')
            ->where('us.user_id', $user->id)
            ->where('us.status', 'approved')
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->where('us.role_name', 'workman')
                        ->where('us.service_handle', 'workman-delivery-boy');
                })->orWhere(function ($qq) {
                    $qq->where('us.role_name', 'vendor');
                });
            })
            ->select('st.id', 'st.name', 'st.slug', 'st.img_src')
            ->distinct()
            ->orderBy('st.name')
            ->get();

        return response()->json([
            'data' => $types
        ]);
    }
    public function getAddItemProducts(Request $request)
    {
        $request->validate([
            'subscription_type_id' => 'required|integer',
        ]);

        // 1️⃣ Get allowed subtypes
        $subtypes = DB::table('subscription_sub_types')
            ->where('subscription_type_id', $request->subscription_type_id)
            ->where('status', 'active')
            ->pluck('slug')
            ->toArray();

        if (empty($subtypes)) {
            return response()->json(['data' => []]);
        }

        // 2️⃣ Get products matching those subtypes
        $products = DB::table('products')
            ->whereIn('product_sub_type', $subtypes)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhere('status', 'active');
            })
            ->select(
                'product_id',
                'title',
                'img_src',
                'product_sub_type'
            )
            ->orderBy('title')
            ->get();

        return response()->json([
            'data' => $products,
        ]);
    }

    public function updateItemQty(Request $request)
    {
        $request->validate([
            'draft_order_item_id' => 'required|integer',
            'qty' => 'required|integer|min:0|max:9999',
        ]);

        $item = DB::table('draft_order_items')
            ->where('id', $request->draft_order_item_id)
            ->where('status', 'active')
            ->first();

        if (!$item) {
            return response()->json([
                'message' => 'Item not found or inactive'
            ], 404);
        }

        $qty = (int) $request->qty;

        // ✅ ADD THIS BLOCK HERE
        if ($qty === 0) {
            DB::table('draft_order_items')
                ->where('id', $request->draft_order_item_id)
                ->update([
                    'qty' => 0,
                    'status' => 'inactive', // or delete if you prefer
                    'updated_at' => now(),
                ]);

            return response()->json([
                'message' => 'Item set to 0 (inactive)',
                'qty' => 0,
            ]);
        }

        DB::table('draft_order_items')
            ->where('id', $request->draft_order_item_id)
            ->update([
                'qty' => $qty,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Quantity updated',
            'qty' => $qty,
        ]);
    }
    public function addItemOptions(Request $request)
    {
        $subscriptionTypeId = (int) $request->query('subscription_type_id', 0);
        $customerId = (int) $request->query('customer_id', 0);

        $subtypeSlug = trim((string) $request->query('subtype_slug', ''));

        if ($subscriptionTypeId <= 0) {
            return response()->json([
                'message' => 'subscription_type_id is required',
            ], 422);
        }

        $subTypes = DB::table('subscription_sub_types')
            ->where('subscription_type_id', $subscriptionTypeId)
            ->where('status', 'active')
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        // ✅ pick default subtype if not sent
        if ($subtypeSlug === '') {
            $subtypeSlug = (string) optional($subTypes->first())->slug;
        }

        $products = collect();
        if ($subtypeSlug !== '') {
            $products = DB::table('products as p')
                ->leftJoin('variants as v', function ($join) {
                    $join->on('v.product_id', '=', 'p.product_id')
                        ->where('v.position', '=', 1);   // ✅ default variant
                })
                ->where('p.product_sub_type', $subtypeSlug)
                ->select(
                    'p.product_id',
                    'p.title',
                    'p.img_src',
                    'p.product_sub_type',
                    'v.variant_id as default_variant_id',
                    'v.price as variant_price',
                    'v.sku as variant_sku'
                )
                ->orderBy('p.title')
                ->get();
        }

        return response()->json([
            'customer_id' => $customerId,
            'subscription_type_id' => $subscriptionTypeId,
            'selected_subtype_slug' => $subtypeSlug,  // ✅ helpful for Flutter
            'sub_types' => $subTypes,
            'products' => $products,
        ]);
    }

    public function createOrderFromMyWork(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|integer',
            'items'       => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:0|max:9999',
        ]);

        $deliveryDate = $request->input('delivery_date')
            ? Carbon::parse($request->delivery_date)->toDateString()
            : Carbon::today()->toDateString();

        return DB::transaction(function () use ($request, $deliveryDate) {

            // 1️⃣ Find today order (same customer, same day)
            $order = Order::where('customer_id', $request->customer_id)
                ->whereDate('delivery_date', $deliveryDate)
                ->orderByDesc('id')
                ->first();

            $isNew = false;

            // 2️⃣ If not exists → create
            if (!$order) {
                $isNew = true;

                $order = Order::create([
                    'customer_id'    => $request->customer_id,
                    'draft_order_id' => $request->draft_order_id ?? null,
                    'zone_id'        => $request->zone_id ?? null,
                    'order_type'     => 'subscription',
                    'status'         => 'pending',
                    'currency_code'  => 'INR',

                    // ✅ delivery tracking
                    'delivery_date'   => $deliveryDate,
                    'delivery_status' => 'delivered',
                    'delivered_at'    => now(),
                    'delivered_by'    => Auth::id(),

                    'created_at'     => now(),
                ]);
            } else {
                // If order exists, optionally link missing ids
                if (!$order->draft_order_id && $request->draft_order_id) {
                    $order->draft_order_id = $request->draft_order_id;
                }
                if (!$order->zone_id && $request->zone_id) {
                    $order->zone_id = $request->zone_id;
                }
                $order->save();
            }

            // 3️⃣ Upsert order items (update qty if exists, else create)
            foreach ($request->items as $item) {


                $pid = $item['product_id'] ?? null;
                $vid = $item['variant_id'] ?? null;

                $title   = $item['title'] ?? 'Item';
                $variant = $item['variant'] ?? null;
                $img     = $item['image_url'] ?? null;

                $qty = (int) ($item['quantity'] ?? 1);
                // ✅ ADD THIS

                if ($qty === 0) {

                    $oiQuery = OrderItem::where('order_id', $order->id);

                    if (!empty($pid)) {
                        $oiQuery->where('product_id', $pid);
                        if (!empty($vid)) $oiQuery->where('variant_id', $vid);
                        else $oiQuery->whereNull('variant_id');
                    } else {
                        $oiQuery->where('title', $title);
                        if (!empty($variant)) $oiQuery->where('variant', $variant);
                    }

                    $oi = $oiQuery->first();

                    if ($oi) {
                        $meta = $oi->meta ?? [];
                        if (!is_array($meta)) $meta = (array)$meta;

                        $meta['skipped'] = true;
                        $meta['updated_by'] = 'my_work_save';
                        $meta['updated_at'] = now()->toDateTimeString();
                        $meta['source'] = $item['source'] ?? ($meta['source'] ?? null);

                        $oi->meta = $meta;
                        $oi->quantity = 0;
                        $oi->unit_price = 0;
                        $oi->line_total = 0;
                        $oi->title = $title;
                        $oi->variant = $variant;
                        $oi->image_url = $img;
                        $oi->actuals_date = $deliveryDate;
                        $oi->save();
                    } else {
                        OrderItem::create([
                            'order_id'     => $order->id,
                            'product_id'   => $pid,
                            'variant_id'   => $vid,
                            'title'        => $title,
                            'variant'      => $variant,
                            'image_url'    => $img,
                            'quantity'     => 0,
                            'unit_price'   => 0,
                            'line_total'   => 0,
                            'actuals_date' => $deliveryDate,
                            'meta'         => [
                                'created_by' => 'my_work_save',
                                'source'     => $item['source'] ?? null,
                                'skipped'    => true,
                            ],
                        ]);
                    }

                    continue; // ✅ IMPORTANT
                }

                $unitPrice = 0;
                $lineTotal = 0;

                $doiId = $item['draft_order_item_id'] ?? null;

                if ($doiId) {
                    $doi = DraftOrderItem::find($doiId);
                    if ($doi && $doi->price_snapshot) {

                        $snap = $doi->price_snapshot;

                        // ✅ If price_snapshot is a plain number like 36.00
                        if (is_numeric($snap)) {
                            $unitPrice = (float) $snap;
                        } elseif (is_string($snap)) {
                            // string number or json
                            if (is_numeric($snap)) {
                                $unitPrice = (float) $snap;
                            } else {
                                $decoded = json_decode($snap, true);
                                if (is_numeric($decoded)) {
                                    $unitPrice = (float) $decoded;
                                } elseif (is_array($decoded)) {
                                    $unitPrice = (float)(
                                        $decoded['unit_price']
                                        ?? $decoded['price']
                                        ?? $decoded['selling_price']
                                        ?? 0
                                    );
                                }
                            }
                        } elseif (is_array($snap)) {
                            $unitPrice = (float)(
                                $snap['unit_price']
                                ?? $snap['price']
                                ?? $snap['selling_price']
                                ?? 0
                            );
                        }
                    }
                }

                $lineTotal = round($unitPrice * $qty, 2);


                // ✅ Match existing order_item by product_id + variant_id when possible
                $oiQuery = OrderItem::where('order_id', $order->id);

                if (!empty($pid)) {
                    $oiQuery->where('product_id', $pid);
                    // variant_id can be null in DB
                    if (!empty($vid)) {
                        $oiQuery->where('variant_id', $vid);
                    } else {
                        $oiQuery->whereNull('variant_id');
                    }
                } else {
                    // fallback match (if product_id missing)
                    $oiQuery->where('title', $title);
                    if (!empty($variant)) $oiQuery->where('variant', $variant);
                }

                $oi = $oiQuery->first();

                if ($oi) {
                    // ✅ update quantity (and refresh meta info)
                    $meta = $oi->meta ?? [];
                    if (!is_array($meta)) $meta = (array)$meta;
                    $meta['updated_by'] = 'my_work_save';
                    $meta['updated_at'] = now()->toDateTimeString();
                    $meta['source'] = $item['source'] ?? ($meta['source'] ?? null);
                    $oi->meta = $meta;

                    $oi->quantity = $qty;
                    $oi->unit_price = $unitPrice;
                    $oi->line_total = $lineTotal;
                    $oi->title = $title;
                    $oi->variant = $variant;
                    $oi->image_url = $img;
                    $oi->actuals_date = $deliveryDate;
                    $oi->save();

                    activity('order_item')
                        ->performedOn($oi)
                        ->causedBy(Auth::user())
                        ->withProperties([
                            'source' => 'my_work',
                            'reason' => 'quantity_adjusted',
                        ])
                        ->log('updated');
                } else {
                    // ✅ create new line
                    OrderItem::create([
                        'order_id'     => $order->id,
                        'product_id'   => $pid,
                        'variant_id'   => $vid,
                        'title'        => $title,
                        'variant'      => $variant,
                        'image_url'    => $img,
                        'quantity'     => $qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'actuals_date' => $deliveryDate,
                        'meta'         => [
                            'created_by' => 'my_work_save',
                            'source'     => $item['source'] ?? null,
                        ],
                    ]);
                }
            }
            $order->delivery_date   = $order->delivery_date ?? $deliveryDate;
            $order->delivery_status = 'delivered';
            $order->delivered_at    = now();
            $order->delivered_by    = Auth::id();
            $order->save();


            $subscriptionTypeId = (int) ($request->subscription_type_id ?? 0);

            $this->tryCreateAgentDeliveredEvent(
                (int) ($order->zone_id ?? 0),
                $subscriptionTypeId,
                \Carbon\Carbon::parse((string) $order->delivery_date)->toDateString()
            );



            // ✅ Trigger daily zone reconciliation after delivery save
            $subscriptionTypeId = (int) ($request->subscription_type_id ?? 0);


            $day  = Carbon::parse($order->delivery_date)->format('l');
            $date = Carbon::parse($order->delivery_date)->format('d M');

            $orderItemsForNotification = OrderItem::query()
                ->where('order_id', $order->id)
                ->get()
                ->map(function ($oi) {
                    return [
                        'name' => $oi->title ?? 'Item',
                        'qty'  => (float) $oi->quantity,
                        'unit' => $oi->meta['unit'] ?? null,
                    ];
                })
                ->values()
                ->all();

            SendPushToUserJob::dispatch((int) $order->customer_id, [
                'title' => "Your order on {$day}",
                'body'  => "Your items for {$day} - {$date}\nhave been delivered",

                'data'  => [
                    'type' => 'order',
                    'action' => 'delivered',

                    'order_id' => (string) $order->id,
                    'entity_id' => (string) $order->id,

                    'screen' => 'order_detail',
                    'deeplink' => 'dayli://orders/' . $order->id,

                    'delivery_date' => (string) $order->delivery_date,
                    'day' => $day,
                    'date_label' => $date,

                    'items' => $orderItemsForNotification,

                    'cta_primary' => 'view_details',
                    'cta_secondary' => 'close',
                ],
            ]);

            return response()->json([
                'order_id'        => $order->id,
                'status'          => $isNew ? 'created' : 'updated',
                'delivery_date'   => $order->delivery_date,
                'delivery_status' => $order->delivery_status,
            ]);
        });
    }
    public function notifications(Request $request)
    {
        $user = $request->user();

        $data = DB::table('notifications')
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'ok' => true,
            'data' => $data,
        ]);
    }
    public function operatorCustomers(Request $request)
    {
        $user = $request->user();

        if (! $user->hasAnyRole(['admin', 'zone-manager', 'workman-delivery-boy'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $q = trim((string) $request->query('q', ''));

        $customers = \App\Models\User::query()
            ->role('customer')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($qq) use ($q) {
                    $qq->where('name', 'like', "%{$q}%")
                        ->orWhere('phone', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%");
                });
            })
            ->select('id', 'name', 'phone', 'email', 'zone_id')
            ->orderBy('name')
            ->limit(30)
            ->get();

        return response()->json([
            'data' => $customers,
        ]);
    }
}
