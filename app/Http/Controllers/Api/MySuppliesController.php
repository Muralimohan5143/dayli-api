<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DraftOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $vendorId = (int) $user->id;
        $subTypeId = (int) $request->query('subscription_type_id');

        $today = now()->toDateString();

        $rows = DB::table('draft_order_items as doi')
            ->join('draft_orders as dor', 'dor.id', '=', 'doi.draft_order_id')
            ->join('sub_change_requests as scr', 'scr.id', '=', 'dor.change_request_id')
            ->join('users as u', 'u.id', '=', 'scr.for_user_id') // customer
            ->where('scr.party_type', 'supplier')
            ->where('scr.by_user_id', $vendorId)
            ->where('scr.subscription_type_id', $subTypeId)
            ->whereIn('scr.status', ['pending', 'approved'])

            ->where(function ($q) use ($today) {
                $q->whereNull('doi.start_date')
                    ->orWhereDate('doi.start_date', '<=', $today);
            })
            ->where(function ($q) use ($today) {
                $q->whereNull('doi.end_date')
                    ->orWhereDate('doi.end_date', '>=', $today);
            })
            ->select(
                'doi.id',
                'u.name as customer_name',
                'u.phone as customer_phone',
                'doi.qty',
                'doi.unit',
                'doi.product_id',
                'doi.variant_id'
            )
            ->orderBy('u.name')
            ->get();

        return response()->json(['data' => $rows]);
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
        $this->ensureVendor($request);

        // Minimal placeholder: return subscription types + sub types
        $types = DB::table('subscription_types')->select(['id', 'name'])->orderBy('name')->get();
        $subTypes = DB::table('subscription_sub_types')->select(['id', 'subscription_type_id', 'name'])->orderBy('name')->get();

        return response()->json([
            'data' => [
                'subscription_types'     => $types,
                'subscription_sub_types' => $subTypes,
            ],
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
        $this->ensureVendor($request);

        // Minimal placeholder to avoid breaking compile; replace with your real code.
        return response()->json([
            'message' => 'TODO: implement createOrderFromMySupplies (copy from createOrderFromMyWork)',
        ], 422);
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
            ->where('scr.status', 'approved') // important
            ->select('st.id', 'st.name')
            ->distinct()
            ->orderBy('st.name')
            ->get();

        return response()->json(['data' => $types]);
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
