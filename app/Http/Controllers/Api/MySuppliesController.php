<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DraftOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Jobs\SendPushToUserJob;
use App\Models\OutboxEvent;

class MySuppliesController extends Controller
{
    /**
     * Vendor "My Supplies" = same UX as delivery boy "My Work",
     * but ALWAYS supplier side:
     *   - scr.party_type = 'supplier'
     *   - scr.by_user_id = logged-in vendor
     *
     * NOTE:
     * I kept method names exactly as you routed in api.php.
     * If your existing MyWork uses different table/columns, adjust only inside queries.
     */

    // --------------------------------------------
    // Guards / helpers
    // --------------------------------------------
    private function ensureVendor(Request $request): void
    {
        $u = $request->user();
        if (!$u) abort(401, 'Unauthenticated');

        // ✅ allow vendor & vendor-* roles
        if (method_exists($u, 'hasRole') && method_exists($u, 'hasAnyRole')) {
            $ok = $u->hasRole('vendor') || $u->hasAnyRole([
                'vendor-milk',
                'vendor-vegetable',
                'vendor-meat',
                'vendor-grocery',
            ]);
            if (!$ok) abort(403, 'Forbidden');
        }
    }

    private function vendorId(Request $request): int
    {
        $u = $request->user();
        return (int) ($u?->id ?? 0);
    }

    /**
     * Base supplier-side join: scr -> draft_orders -> orders
     * IMPORTANT: supplier filter is here.
     */
    private function baseSupplierOrdersQuery(int $vendorId)
    {
        return DB::table('orders as o')
            ->join('draft_orders as dor', 'dor.id', '=', 'o.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'dor.change_request_id')
            ->where('scr.party_type', 'supplier')      // ✅ supplier only
            ->where('scr.by_user_id', $vendorId);      // ✅ vendor is creator/supplier
    }

    // --------------------------------------------
    // Routes
    // --------------------------------------------

    /**
     * GET /api/my-supplies
     * Optional: return a small state payload (like MyWorkController::index)
     */
    public function index(Request $request)
    {
        $this->ensureVendor($request);

        return response()->json([
            'message' => 'My Supplies API',
            'role'    => 'vendor',
        ]);
    }

    /**
     * GET /api/my-supplies/summary
     * Summary cards (counts/qty) for vendor supply dashboard.
     */
    public function summary(Request $request)
    {
        $this->ensureVendor($request);
        $vendorId = $this->vendorId($request);

        // You can align this with your MyWorkController::summary shape.
        $asOf = $request->query('date')
            ? \Carbon\Carbon::parse($request->query('date'))->startOfDay()
            : \Carbon\Carbon::today();

        $asOfDate = $asOf->toDateString();

        // ✅ pending / delivered counts for that date
        $base = $this->baseSupplierOrdersQuery($vendorId)
            ->whereNotNull('o.delivery_date')
            ->whereDate('o.delivery_date', $asOfDate);

        $pendingCount   = (clone $base)->where('o.delivery_status', 'pending')->count();
        $deliveredCount = (clone $base)->where('o.delivery_status', 'delivered')->count();

        return response()->json([
            'data' => [
                'date'            => $asOfDate,
                'pending_orders'  => (int) $pendingCount,
                'delivered_orders' => (int) $deliveredCount,
            ],
        ]);
    }

    /**
     * GET /api/my-supplies/orders
     *
     * Supports same pattern you used in MyWork:
     *   ?mode=dates            -> returns available dates
     *   ?mode=list&date=YYYY-MM-DD -> returns orders list for that date
     *
     * IMPORTANT: Supplier filter is baked in via baseSupplierOrdersQuery().
     */
    public function orders(Request $request)
    {
        return $this->mySuppliesOrders($request);
    }



    public function mySuppliesOrders(Request $request)
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $vendorId = (int) $user->id;

        $subTypeId = (int) $request->query('subscription_type_id', 0);
        if ($subTypeId <= 0) {
            return response()->json([
                'message' => 'subscription_type_id is required',
                'data'    => [],
            ], 422);
        }

        $today = \Carbon\Carbon::today()->toDateString();

        // same style as MyWorkOrders: accept delivery_date or deliveryDate
        $requestedDate = $request->query('delivery_date') ?? $request->query('deliveryDate');
        $targetDate = $requestedDate ? \Carbon\Carbon::parse($requestedDate)->toDateString() : $today;

        $mode = (string) $request->query('mode', '');     // mode=dates
        $type = (string) $request->query('status', 'today'); // today|pending|completed

        // ---------------------------------------------------------
        // 1) Base rows (supplier side subscriptions -> customer list)
        // ---------------------------------------------------------
        $baseRows = DB::table('draft_order_items as doi')
            ->join('draft_orders as do', 'do.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'do.change_request_id')
            ->join('users as u', 'u.id', '=', 'scr.for_user_id') // customer who receives supply
            ->where('scr.party_type', 'supplier')
            ->where('scr.by_user_id', $vendorId)
            ->where('scr.subscription_type_id', $subTypeId)
            ->whereIn('scr.status', ['pending', 'approved'])
            ->where('do.status', 'active')
            ->where('doi.status', 'active')
            // ✅ only items active on targetDate (prevents today's new item showing in old dates)
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
                'scr.zone_id as zone_id',
                'scr.for_user_id as customer_id',
                DB::raw("COALESCE(NULLIF(u.name,''), NULLIF(u.display_name,''), NULLIF(TRIM(CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,''))), ''), 'Customer') as customer_name"),
                DB::raw("COALESCE(u.phone_normalized, u.phone, '') as customer_phone"),
                DB::raw("COALESCE(u.nagar, '') as nagar"),
                DB::raw("COALESCE(u.address, '') as address"),
            ])
            ->get();

        if ($baseRows->isEmpty()) {
            // still support mode=dates for empty
            if ($mode === 'dates') {
                return response()->json([
                    'subscription_type_id' => (int) $subTypeId,
                    'vendor_id'            => (int) $vendorId,
                    'pending_dates'        => [],
                    'done_dates'           => [],
                ]);
            }

            return response()->json([
                'subscription_type_id' => (int) $subTypeId,
                'vendor_id'            => (int) $vendorId,
                'date'                 => $targetDate,
                'data'                 => [],
            ]);
        }

        // ---------------------------------------------------------
        // 2) Ensure daily order rows exist (vendor + draft_order_id + date)
        // ---------------------------------------------------------
        $draftOrderIds = $baseRows->pluck('draft_order_id')->filter()->unique()->values()->all();

        // existing order rows for this vendor + targetDate + those draft_orders
        $existingDoIds = DB::table('orders')
            ->where('vendor_id', $vendorId)
            ->whereDate('delivery_date', $targetDate)
            ->whereIn('draft_order_id', $draftOrderIds)
            ->pluck('draft_order_id')
            ->unique()
            ->all();

        $missingDoIds = array_values(array_diff($draftOrderIds, $existingDoIds));

        if (!empty($missingDoIds)) {
            $now = \Carbon\Carbon::now();

            // map draft_order_id -> zone_id (best effort)
            $zoneByDo = $baseRows->groupBy('draft_order_id')->map(function ($rows) {
                return (int) ($rows->first()->zone_id ?? 0);
            });

            $customerByDo = $baseRows->groupBy('draft_order_id')->map(function ($rows) {
                return (int) ($rows->first()->customer_id ?? 0);
            });

            $insertRows = array_map(function ($doId) use ($vendorId, $targetDate, $now, $zoneByDo, $customerByDo) {
                $z = (int) ($zoneByDo[$doId] ?? 0);
                $customerId = (int) ($customerByDo[$doId] ?? 0);

                return [
                    'order_type'       => 'subscription',
                    'customer_id'      => $customerId,
                    'vendor_id'        => (int) $vendorId,
                    'zone_id'          => $z > 0 ? $z : null,
                    'draft_order_id'   => (int) $doId,
                    'delivery_date'    => $targetDate,
                    'delivery_status'  => 'pending',
                    'status'           => 'pending',
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }, $missingDoIds);

            DB::table('orders')->insert($insertRows);
        }

        // ---------------------------------------------------------
        // 3) mode=dates (pending/done date options for modal)
        // ---------------------------------------------------------
        if ($mode === 'dates') {

            $pendingDates = DB::table('orders')
                ->where('vendor_id', $vendorId)
                ->whereIn('draft_order_id', $draftOrderIds)
                ->whereNotNull('delivery_date')
                ->where('delivery_status', 'pending')
                ->whereDate('delivery_date', '<=', $today)
                ->selectRaw("DATE(delivery_date) as d")
                ->groupBy('d')
                ->orderByDesc('d')
                ->pluck('d')
                ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
                ->values();

            $doneDates = DB::table('orders')
                ->where('vendor_id', $vendorId)
                ->whereIn('draft_order_id', $draftOrderIds)
                ->whereNotNull('delivery_date')
                ->where('delivery_status', 'delivered')
                ->selectRaw("DATE(delivery_date) as d")
                ->groupBy('d')
                ->orderByDesc('d')
                ->pluck('d')
                ->map(fn($d) => \Carbon\Carbon::parse($d)->format('Y-m-d'))
                ->values();

            return response()->json([
                'subscription_type_id' => (int) $subTypeId,
                'vendor_id'            => (int) $vendorId,
                'pending_dates'        => $pendingDates,
                'done_dates'           => $doneDates,
            ]);
        }

        // ---------------------------------------------------------
        // 4) Load today's/dates orders status map (by draft_order_id)
        // ---------------------------------------------------------
        $orders = DB::table('orders')
            ->select('id', 'draft_order_id', 'delivery_status')
            ->where('vendor_id', $vendorId)
            ->whereIn('draft_order_id', $draftOrderIds)
            ->whereDate('delivery_date', $targetDate)
            ->orderByDesc('id')
            ->get();

        // latest per draft_order_id
        $ordersMap = $orders->groupBy('draft_order_id')->map(function ($g) {
            return $g->first();
        });

        // optional tab filter: pending/completed
        if ($type === 'pending' || $type === 'completed') {
            $want = ($type === 'pending') ? 'pending' : 'delivered';
            $allowedDoIds = $ordersMap
                ->filter(fn($o) => ($o->delivery_status ?? '') === $want)
                ->keys()
                ->map(fn($k) => (int) $k)
                ->values()
                ->all();

            // keep only rows whose draft_order is in the allowed set
            $baseRows = $baseRows->filter(function ($r) use ($allowedDoIds) {
                return in_array((int) $r->draft_order_id, $allowedDoIds, true);
            })->values();
        }


        // ---------------------------------------------------------
        // ✅ 4.5) Load order_items qty map (override draft qty)
        // ---------------------------------------------------------
        $orderIds = $ordersMap->map(fn($o) => (int) ($o->id ?? 0))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $orderItemsMap = collect(); // order_id => (product_id|variant_id => quantity)

        if (!empty($orderIds)) {
            $ois = DB::table('order_items')
                ->select('order_id', 'product_id', 'variant_id', 'quantity')
                ->whereIn('order_id', $orderIds)
                ->get();

            $orderItemsMap = $ois
                ->groupBy('order_id')
                ->map(function ($items) {
                    return $items->keyBy(function ($oi) {
                        $pid = (int) ($oi->product_id ?? 0);
                        $vid = (int) ($oi->variant_id ?? 0);
                        return $pid . '|' . $vid;
                    });
                });
        }
        // ---------------------------------------------------------
        // 5) Load item+product details like MyWorkOrders
        // ---------------------------------------------------------
        $ids = $baseRows->pluck('draft_order_item_id')->all();
        $customerMap = $baseRows->keyBy('draft_order_item_id');

        $items = DraftOrderItem::query()
            ->with('product')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        $rows = collect($ids)->map(function ($id) use ($items, $customerMap, $ordersMap, $orderItemsMap) {
            $item = $items->get($id);
            $c    = $customerMap->get($id);

            if (!$item || !$c) return null;

            $doId = (int) ($c->draft_order_id ?? 0);
            $o    = $ordersMap->get($doId);

            $oiQty = null;
            if ($o) {
                $key = ((int)$item->product_id) . '|' . ((int)$item->variant_id);
                $oi = data_get($orderItemsMap, ((int)$o->id) . '.' . $key);
                if ($oi) $oiQty = (float) $oi->quantity;
            }

            return [
                'draft_order_item_id' => (int) $item->id,
                'draft_order_id'      => (int) $item->draft_order_id,

                // customer receiving supply
                'customer_id'         => (int) ($c->customer_id ?? 0),
                'customer_name'       => $c->customer_name ?? 'Customer',
                'customer_phone'      => $c->customer_phone ?? '',

                'nagar'               => $c->nagar ?? '',
                'address'             => $c->address ?? '',

                // daily status for vendor supply order (by draft_order_id)
                'today_order_id'      => $o ? (int) $o->id : 0,
                'delivery_status'     => $o->delivery_status ?? 'pending',

                // product details
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

        return response()->json([
            'subscription_type_id' => (int) $subTypeId,
            'vendor_id'            => (int) $vendorId,
            'date'                 => $targetDate,
            'data'                 => $rows,
        ]);
    }

    /**
     * GET /api/my-supplies/add-item-products?subscription_type_id=1
     * You can mirror MyWork's product picker.
     *
     * NOTE: I don’t know your products schema exactly here.
     * Keep it minimal; replace with your existing logic from MeController::getAddItemProducts().
     */
    public function getAddItemProducts(Request $request)
    {
        $this->ensureVendor($request);

        $subscriptionTypeId = (int) $request->query('subscription_type_id', 0);

        // TODO: replace with your real query from MeController::getAddItemProducts()
        $products = DB::table('products')
            ->when($subscriptionTypeId > 0, fn($q) => $q->where('subscription_type_id', $subscriptionTypeId))
            ->select(['id', 'title', 'product_type', 'img_src'])
            ->orderBy('title')
            ->limit(500)
            ->get();

        return response()->json(['data' => $products]);
    }

    /**
     * GET /api/my-supplies/add-item-options
     * TODO: copy exact code from MeController::addItemOptions, but ensure supplier filter if needed.
     */
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

    /**
     * POST /api/my-supplies/item/update-qty
     * Body: { draft_order_item_id, qty }
     *
     * This updates quantity for a subscription item used in supplies flow.
     */
    public function updateItemQty(Request $request)
    {
        $this->ensureVendor($request);

        $data = $request->validate([
            'draft_order_item_id' => ['required', 'integer'],
            'qty'                 => ['required', 'numeric', 'min:0'],
        ]);

        $item = DraftOrderItem::query()->findOrFail((int) $data['draft_order_item_id']);

        // Optional: enforce vendor owns this supplier-side subscription
        // by checking scr.party_type + scr.by_user_id through joins.
        $vendorId = $this->vendorId($request);
        $ok = DB::table('draft_order_items as doi')
            ->join('draft_orders as dor', 'dor.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'dor.change_request_id')
            ->where('doi.id', $item->id)
            ->where('scr.party_type', 'supplier')
            ->where('scr.by_user_id', $vendorId)
            ->exists();

        if (!$ok) abort(403, 'Forbidden');

        $item->qty = (float) $data['qty'];
        $item->save();

        return response()->json(['message' => 'updated', 'data' => ['id' => $item->id, 'qty' => (float) $item->qty]]);
    }

    /**
     * POST /api/my-supplies/create-order
     * This should create a new order for supplier flow (if your UI uses it).
     *
     * TODO: Copy your exact logic from MeController::createOrderFromMyWork and
     * enforce supplier filter (scr.party_type='supplier').
     */
    public function createOrderFromMySupplies(Request $request)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $vendorId = (int) $user->id;

        // ✅ Always validate items
        $request->validate([
            'items'            => 'required|array|min:1',
            'items.*.quantity' => 'required|integer|min:0|max:9999',
            'delivery_date' => 'nullable|date',
        ]);

        // ✅ Resolve SCR id (direct OR fallback)
        $scrId = (int) ($request->input('change_request_id') ?? 0);

        if ($scrId <= 0) {
            $request->validate([
                'customer_id'          => 'required|integer',
                'subscription_type_id' => 'required|integer',
            ]);

            $scrId = (int) DB::table('sub_change_requests')
                ->where('party_type', 'supplier')
                ->where('by_user_id', $vendorId)
                ->where('for_user_id', (int) $request->customer_id)
                ->where('subscription_type_id', (int) $request->subscription_type_id)
                ->whereIn('status', ['pending', 'approved'])
                ->orderByDesc('id')
                ->value('id');
        }

        if ($scrId <= 0) {
            return response()->json(['message' => 'Invalid supplier SCR'], 422);
        }

        $deliveryDate = $request->input('delivery_date')
            ? \Carbon\Carbon::parse($request->delivery_date)->toDateString()
            : \Carbon\Carbon::today()->toDateString();

        return DB::transaction(function () use ($request, $deliveryDate, $vendorId, $scrId) {

            // 1️⃣ Load SCR (must belong to this vendor + supplier)
            $scr = DB::table('sub_change_requests')
                ->where('id', $scrId)
                ->where('party_type', 'supplier')
                ->where('by_user_id', $vendorId)
                ->first();

            if (! $scr) {
                return response()->json(['message' => 'Invalid supplier SCR'], 422);
            }

            $customerId = (int) $scr->for_user_id;
            $zoneId     = $scr->zone_id ?? null;

            // 2️⃣ Find today order (same customer, same vendor, same day)
            $order = \App\Models\Order::where('customer_id', $customerId)
                ->where('vendor_id', $vendorId)
                ->whereDate('delivery_date', $deliveryDate)
                ->orderByDesc('id')
                ->first();

            $isNew = false;

            // 3️⃣ If not exists → create (PENDING delivery)
            if (! $order) {
                $isNew = true;

                $order = \App\Models\Order::create([
                    'customer_id'    => $customerId,
                    'vendor_id'      => $vendorId,
                    'draft_order_id' => $request->draft_order_id ?? null, // optional if UI sends
                    'zone_id'        => $request->zone_id ?? $zoneId,

                    'order_type'     => 'subscription',
                    'status'         => 'pending',
                    'currency_code'  => 'INR',

                    // ✅ supply entry should not mark delivered
                    'delivery_date'   => $deliveryDate,
                    'delivery_status' => 'pending',

                    'created_at' => now(),
                ]);
            } else {
                // If order exists, link missing ids
                if (! $order->draft_order_id && $request->draft_order_id) {
                    $order->draft_order_id = $request->draft_order_id;
                }
                if (! $order->zone_id && ($request->zone_id || $zoneId)) {
                    $order->zone_id = $request->zone_id ?? $zoneId;
                }
                if (! $order->vendor_id) {
                    $order->vendor_id = $vendorId;
                }
                $order->save();
            }

            // 4️⃣ Upsert order items (same logic as MyWork)
            foreach ($request->items as $item) {

                $pid = $item['product_id'] ?? null;
                $vid = $item['variant_id'] ?? null;

                $title   = $item['title'] ?? 'Item';
                $variant = $item['variant'] ?? null;
                $img     = $item['image_url'] ?? null;

                $qty = (int) ($item['quantity'] ?? 1);
                // ✅ if qty = 0 keep row in order_items (do NOT delete)
                if ($qty === 0) {

                    $oiQuery = \App\Models\OrderItem::where('order_id', $order->id);

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
                        $meta['updated_by'] = 'my_supplies_save';
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
                        \App\Models\OrderItem::create([
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
                                'created_by' => 'my_supplies_save',
                                'source'     => $item['source'] ?? null,
                                'skipped'    => true,
                            ],
                        ]);
                    }

                    continue;
                }

                $unitPrice = 0;
                $lineTotal = 0;

                $doiId = $item['draft_order_item_id'] ?? null;

                if ($doiId) {
                    $doi = \App\Models\DraftOrderItem::find($doiId);
                    if ($doi && $doi->price_snapshot) {

                        $snap = $doi->price_snapshot;

                        if (is_numeric($snap)) {
                            $unitPrice = (float) $snap;
                        } elseif (is_string($snap)) {
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
                $oiQuery = \App\Models\OrderItem::where('order_id', $order->id);

                if (! empty($pid)) {
                    $oiQuery->where('product_id', $pid);
                    if (! empty($vid)) {
                        $oiQuery->where('variant_id', $vid);
                    } else {
                        $oiQuery->whereNull('variant_id');
                    }
                } else {
                    $oiQuery->where('title', $title);
                    if (! empty($variant)) $oiQuery->where('variant', $variant);
                }

                $oi = $oiQuery->first();

                if ($oi) {
                    $meta = $oi->meta ?? [];
                    if (! is_array($meta)) $meta = (array) $meta;
                    $meta['updated_by'] = 'my_supplies_save';
                    $meta['updated_at'] = now()->toDateTimeString();
                    $meta['source']     = $item['source'] ?? ($meta['source'] ?? null);
                    $oi->meta = $meta;

                    $oi->quantity     = $qty;
                    $oi->unit_price   = $unitPrice;
                    $oi->line_total   = $lineTotal;
                    $oi->title        = $title;
                    $oi->variant      = $variant;
                    $oi->image_url    = $img;
                    $oi->actuals_date = $deliveryDate;
                    $oi->save();
                } else {
                    \App\Models\OrderItem::create([
                        'order_id'     => $order->id,
                        'product_id'   => $pid,
                        'variant_id'   => $vid,
                        'title'        => $title,
                        'variant'      => $variant,
                        'image_url'    => $img,
                        'quantity'     => $qty,
                        'unit_price'   => $unitPrice,
                        'line_total'   => $lineTotal,
                        'actuals_date' => $deliveryDate,
                        'meta'         => [
                            'created_by' => 'my_supplies_save',
                            'source'     => $item['source'] ?? null,
                        ],
                    ]);
                }
            }

            // 5️⃣ Ensure supply order stays pending
            $order->delivery_date   = $order->delivery_date ?? $deliveryDate;
            $order->delivery_status = 'delivered';
            $order->delivered_at    = now();
            $order->delivered_by    = Auth::id();
            $order->save();

            // 6️⃣ Write outbox event (vendor_supply_entered)
            DB::table('outbox_events')->updateOrInsert(
                [
                    'idempotency_key' => "vendor_supply_entered:order:{$order->id}",
                ],
                [
                    'event_type'     => 'vendor_supply_entered',
                    'aggregate_type' => 'order',
                    'aggregate_id'   => $order->id,
                    'scheduled_at'   => now(),
                    'payload'        => DB::raw(
                        "JSON_OBJECT(
                'vendor_id', {$vendorId},
                'order_id', {$order->id},
                'delivery_date', '{$deliveryDate}',
                'zone_id', " . ($order->zone_id ?? 'NULL') . ",
                'source', 'dayli_app'
            )"
                    ),
                    'status'        => 'pending',
                    'attempts'      => 0,
                    'max_attempts'  => 10,
                    'updated_at'    => now(),
                    'created_at'    => now(),
                ]
            );

            // 7️⃣ Write outbox event for Daily Zone Reconcile
            OutboxEvent::updateOrCreate(
                [
                    'idempotency_key' => "zone_daily_reconcile:zone:" . ($order->zone_id ?? 0) . ":date:{$deliveryDate}:subtype:" . ((int)($scr->subscription_type_id ?? 0)),
                ],
                [
                    'event_type'     => 'zone.daily.reconcile',
                    'aggregate_type' => 'zone',
                    'aggregate_id'   => (int) ($order->zone_id ?? 0),
                    'scheduled_at'   => now(),
                    'payload'        => [
                        'zone_id' => (int) ($order->zone_id ?? 0),
                        'delivery_date' => $deliveryDate,
                        'subscription_type_id' => (int) ($scr->subscription_type_id ?? 0),
                        'delivered_only' => true,
                    ],
                    'status'        => 'pending',
                    'attempts'      => 0,
                    'max_attempts'  => 10,
                ]
            );


            // send push notification
            DB::afterCommit(function () use ($customerId, $order) {
                SendPushToUserJob::dispatch($customerId, [
                    'title' => 'Supply Update',
                    'body'  => 'Vendor updated items for order #' . $order->id,
                    'data'  => [
                        'type' => 'order',
                        'entity_id' => (string) $order->id,
                        'deeplink' => 'dayli://orders/' . $order->id,
                    ],
                ]);
            });
            return response()->json([
                'order_id'        => $order->id,
                'status'          => $isNew ? 'created' : 'updated',
                'delivery_date'   => $order->delivery_date,
                'delivery_status' => $order->delivery_status,
            ]);
        });
    }
    /**
     * GET /api/my-supplies/subscription-types
     * Used for the picker on My Supplies screen.
     *
     * TODO: If you have vendor-specific subscription types, filter here.
     */
    public function subscriptionTypes(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $vendorId = (int) $user->id;

        $types = DB::table('sub_change_requests as scr')
            ->join('subscription_types as st', 'st.id', '=', 'scr.subscription_type_id')
            ->where('scr.party_type', 'supplier')
            ->where('scr.by_user_id', $vendorId)
            ->whereIn('scr.status', ['pending', 'approved'])
            ->select(
                'st.id',
                'st.name',
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('st.id', 'st.name')
            ->orderBy('st.name')
            ->get();

        return response()->json([
            'data' => $types
        ]);
    }


    /**
     * POST /api/my-supplies/{id}/start
     * For parity with delivery tasks.
     *
     * If you don’t have a supply_tasks table yet, keep this as no-op for now.
     */
    public function start(Request $request, int $id)
    {
        $this->ensureVendor($request);

        // TODO: implement if you have a supply_tasks table
        return response()->json(['message' => 'ok', 'data' => ['task_id' => $id, 'status' => 'started']]);
    }

    /**
     * POST /api/my-supplies/{id}/complete
     */
    public function complete(Request $request, int $id)
    {
        $this->ensureVendor($request);

        // TODO: implement if you have a supply_tasks table
        return response()->json(['message' => 'ok', 'data' => ['task_id' => $id, 'status' => 'completed']]);
    }

    /**
     * GET /api/my-supplies/all-products
     * Same as MyWorkController::allProducts (if your UI uses it).
     */
    public function allProducts(Request $request)
    {
        $this->ensureVendor($request);

        $products = DB::table('products')
            ->select(['id', 'title', 'product_type', 'img_src'])
            ->orderBy('title')
            ->limit(1000)
            ->get();

        return response()->json(['data' => $products]);
    }
}
