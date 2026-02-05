<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Models\DraftOrderItem;

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
            ->whereNull('scr.zone_id')
            ->whereNotNull('u.zone_id')
            ->update(['scr.zone_id' => DB::raw('u.zone_id')]);

        // ✅ Role slug from Spatie (first role)
        $roleSlug = null;
        $roles = [];

        if (method_exists($user, 'getRoleNames')) {
            $roles = $user->getRoleNames()->values()->toArray();
            $roleSlug = $roles[0] ?? null;
        }

        return response()->json([
            'id'      => $user->id,
            'name'    => $user->name ?? null,
            'zone_id' => $user->zone_id ?? null,
            'role'    => $roleSlug,
            'roles'   => $roles,
        ]);
    }

    private function currentDeliveryTaskForUser(int $userId)
    {
        return DB::table('delivery_tasks')
            ->where('delivery_exec_id', $userId)
            ->whereIn('status', ['today', 'in_progress'])
            ->orderByDesc('id')
            ->first();
    }

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

        // ✅ 1) Find current delivery task for this delivery boy
        $task = $this->currentDeliveryTaskForUser((int)$user->id);
        if (!$task) {
            return response()->json([
                'message' => 'No active delivery task found for this user',
                'data' => [],
            ], 422);
        }

        // ✅ zone comes from delivery_tasks
        $zoneId = (int) $task->zone_id;

        // ✅ 2) subscription_type_id comes from delivery_tasks.subscription_type_id text
        // ✅ 2) subscription_type_id comes directly from delivery_tasks.subscription_type_id (INT)
        $subTypeId = (int) ($task->subscription_type_id ?? 0);

        if (!$subTypeId) {
            return response()->json([
                'message' => 'Delivery task has no subscription_type_id',
                'data' => [],
            ], 422);
        }

        // ✅ Check requested subscription type from Flutter (left menu click)
        $requestedSubTypeId = (int) $request->query('subscription_type_id');

        if ($requestedSubTypeId && $requestedSubTypeId !== $subTypeId) {
            return response()->json([
                'message' => 'Subscription type not assigned to this delivery task',
                'data' => [],
            ], 403);
        }
        $today = Carbon::today()->toDateString();

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

        $baseRows = DB::table('draft_order_items as doi')
            ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
            ->join('users as u', 'u.id', '=', 'scr.for_user_id')
            ->where('scr.zone_id', $zoneId)
            ->where('scr.subscription_type_id', $subTypeId)
            ->where('do.status', 'active')
            ->where('doi.status', 'active')
            ->orderBy('u.name')
            ->select([
                'doi.id as draft_order_item_id',
                'doi.draft_order_id',
                'scr.for_user_id as customer_id',
                DB::raw("COALESCE(NULLIF(u.name,''), NULLIF(u.display_name,''), NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))), ''), 'Customer') as customer_name"),
                DB::raw("COALESCE(u.phone_normalized, u.phone, '') as customer_phone"),

                // ✅ add these
                DB::raw("COALESCE(u.nagar, '') as nagar"),
                DB::raw("COALESCE(u.address, '') as address"),
            ])
            ->get();

        $ids = $baseRows->pluck('draft_order_item_id')->all();
        $customerMap = $baseRows->keyBy('draft_order_item_id');

        $items = DraftOrderItem::query()
            ->with('product')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $rows = collect($ids)->map(function ($id) use ($items, $customerMap) {
            $item = $items->get($id);
            $c    = $customerMap->get($id);
            if (!$item) return null;

            return [
                'draft_order_item_id' => (int) $item->id,
                'draft_order_id'      => (int) $item->draft_order_id,

                'customer_id'         => (int) ($c->customer_id ?? 0),
                'customer_name'       => $c->customer_name ?? 'Customer',
                'customer_phone'      => $c->customer_phone ?? '',

                'nagar'          => $c->nagar ?? '',
                'address'        => $c->address ?? '',

                'product_title'       => optional($item->product)->title ?? 'Product',
                'image_url'           => optional($item->product)->img_src ?? '',

                'qty'                 => (float) $item->qty,
                'unit'                => $item->unit,
                'frequency_type'      => $item->frequency_type,
                'item_status'         => $item->status ?? 'active',

                'product_id'          => (int) $item->product_id,
                'variant_id'          => (int) $item->variant_id,
            ];
        })->filter()->values();

        $requestedTypeId = (int) $request->query('subscription_type_id', 0);
        if ($requestedTypeId > 0) {
            $subTypeId = $requestedTypeId;
        } else {
            $subTypeId = (int) ($task->subscription_type_id ?? 0);
        }

        return response()->json([
            'delivery_task_id' => (int) $task->id,
            'task_subscription_type_id' => (int) $task->subscription_type_id,
            'subscription_type_id' => (int) $subTypeId,
            'zone_id' => $zoneId,
            'date' => $today,
            'data' => $rows,
        ]);
    }

    public function myWorkSubscriptionTypes(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $tasks = DB::table('delivery_tasks as dt')
            ->join('subscription_types as st', 'st.id', '=', 'dt.subscription_type_id')
            ->where('dt.delivery_exec_id', $user->id)
            ->select('st.id', 'st.name', 'st.slug', 'st.img_src')
            ->distinct()
            ->orderBy('st.name')
            ->get();

        return response()->json([
            'data' => $tasks
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
            'qty' => 'required|integer|min:1|max:9999',
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

        DB::table('draft_order_items')
            ->where('id', $request->draft_order_item_id)
            ->update([
                'qty' => $request->qty,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Quantity updated',
            'qty' => $request->qty,
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
            $products = DB::table('products')
                ->where('product_sub_type', $subtypeSlug)   // ✅ filter one subtype
                ->select('product_id', 'title', 'img_src', 'product_sub_type')
                ->orderBy('title')
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
}
