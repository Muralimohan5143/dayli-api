<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Carbon\Carbon;
use App\Models\SubChangeRequest;
use App\Models\DraftOrder;
use App\Models\DraftOrderItem;
use App\Models\Zone;
use App\Models\User;

class SubscriptionSelectionController extends Controller
{
    /**
     * POST /api/my-subscriptions/store-from-selection
     *
     * Body:
     * {
     *   "items": [
     *     {
     *       "subscription_type_id": 1,
     *       "sub_type_id": 10,
     *       "product_id": 123,
     *       "variant_id": 456,
     *       "price": 120.0,
     *       "mrp": 130.0,
     *       "discount_percent": 5,
     *       "discount_amount": 10,
     *       "cost_price": 100,
     *       "qty": 1,
     *       "unit": "Kg",
     *       "frequency": "daily",
     *       "start_date": "2025-12-16",
     *       "end_date": null
     *     }
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $user = $request->user(); // auth:sanctum user
        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $targetUserId = $this->resolveTargetUserId($request);
        $targetUser = User::findOrFail($targetUserId);

        $zoneId = $this->ensureZoneIdForUser($targetUser) ?? 1;

        if (!$zoneId) {
            return response()->json([
                'ok'      => false,
                'message' => 'Zone not set for this user. Please update profile pincode/address and try again.',
            ], 422);
        }


        // ✅ Decide part_type based on logged-in role
        //$partType = $user->hasRole('vendor') ? 'supplier' : 'consumer';



        $data = $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:users,id'],
            'party_type' => ['required', 'in:consumer,supplier'],

            'items'                        => ['required', 'array', 'min:1'],
            'items.*.subscription_type_id' => ['required', 'integer'],
            'items.*.sub_type_id'          => ['required', 'integer'],
            'items.*.product_id'           => ['required', 'integer'],
            'items.*.variant_id'           => ['required', 'integer'],
            'items.*.price'                => ['required', 'numeric'],
            'items.*.mrp'                  => ['nullable', 'numeric'],
            'items.*.discount_percent'     => ['nullable', 'numeric'],
            'items.*.discount_amount'      => ['nullable', 'numeric'],
            'items.*.cost_price'           => ['nullable', 'numeric'],

            // extra fields from Flutter
            'items.*.qty'                  => ['nullable', 'numeric'],
            'items.*.unit'                 => ['nullable', 'string', 'max:20'],
            'items.*.frequency'            => ['nullable', 'string', 'max:50'],
            'items.*.start_date'           => ['nullable', 'date'],
            'items.*.end_date'             => ['nullable', 'date'],
        ]);

        $items = collect($data['items']);
        $partType = $data['party_type']; // ✅ comes from Flutter


        $defaultFrequency = 'daily';
        $today = Carbon::today()->toDateString();

        // ✅ 0) Quick duplicate check inside the same request payload
        // (same product_id + variant_id repeated in request)
        $dupInRequest = $items->groupBy(function ($r) {
            $r = (array) $r;
            return ((int) $r['product_id']) . '|' . ((int) $r['variant_id']);
        })->filter(fn($g) => $g->count() > 1);

        if ($dupInRequest->isNotEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => 'Duplicate product detected in request payload (same product/variant repeated).',
            ], 422);
        }

        // ✅ 1) Duplicate check against DB (existing active items for this user in pending/approved SCR)
        foreach ($items as $row) {
            $row       = (array) $row;
            $productId = (int) $row['product_id'];
            $variantId = (int) $row['variant_id'];
            $vendorId  = $row['vendor_id'] ?? null; // if later you send vendor_id

            $alreadyExists = DraftOrderItem::where('product_id', $productId)
                ->where('variant_id', $variantId)
                ->where(function ($q) use ($vendorId) {
                    if ($vendorId === null) {
                        $q->whereNull('vendor_id');
                    } else {
                        $q->where('vendor_id', $vendorId);
                    }
                })
                // treat null as active; exclude paused/cancelled
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhere('status', 'active');
                })
                ->whereHas('draftOrder.changeRequest', function ($q) use ($targetUserId) {
                    $q->where('for_user_id', $targetUserId)
                        ->whereIn('status', ['pending', 'approved']);
                })
                ->exists();

            if ($alreadyExists) {
                return response()->json([
                    'ok'      => false,
                    'message' => 'You have already added this product to your subscriptions.',
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            /**
             * ✅ GROUP by (subscription_type_id + sub_type_id)
             * Each group should go into ONE SCR + ONE DraftOrder (REUSED if already exists)
             */
            $grouped = $items->groupBy(function ($row) {
                $row = (array) $row;
                return (int) $row['subscription_type_id'];
            });

            $draftOrders = [];
            $earliestBackfillDate = null;

            foreach ($grouped as $groupKey => $rows) {
                $subTypeIds = $rows->pluck('sub_type_id')
                    ->map(fn($x) => (int) $x)
                    ->unique()
                    ->values()
                    ->all();
                $first = (array) $rows->first();

                $subscriptionTypeId = (int) $first['subscription_type_id'];
                $subTypeId          = (int) $first['sub_type_id'];

                // group defaults
                $groupFrequency = $first['frequency']  ?? $defaultFrequency;
                $groupStartDate = $first['start_date'] ?? $today;
                $groupEndDate   = $first['end_date']   ?? null;

                /**
                 * ✅ REUSE SCR + DraftOrder if already pending/approved for same user + type + subtype
                 * NOTE: This assumes subtypes_json is a JSON column. If it's TEXT, swap the where line.
                 */
                $scr = SubChangeRequest::where('for_user_id', $targetUserId)
                    ->where('party_type', $partType)
                    ->where('subscription_type_id', $subscriptionTypeId)
                    ->whereIn('status', ['pending', 'approved'])
                    ->latest('id')
                    ->first();
                if ($scr) {

                    $existing = json_decode($scr->subtypes_json ?? '[]', true);
                    if (!is_array($existing)) $existing = [];

                    $existingIds = $existing['selected_sub_type_ids'] ?? [];

                    $merged = array_values(array_unique(array_merge($existingIds, $subTypeIds)));

                    $scr->subtypes_json = json_encode([
                        'selected_sub_type_ids' => $merged
                    ]);

                    $scr->save();
                }

                $draft = null;

                if ($scr && $scr->draft_order_id) {
                    $draft = DraftOrder::find($scr->draft_order_id);
                }

                // ✅ Ensure zone_id is set on reused records
                if ($scr && is_null($scr->zone_id)) {
                    $scr->zone_id = $zoneId;
                    $scr->save();
                }
                if ($scr && empty($scr->party_type)) {
                    $scr->party_type = $partType;
                    $scr->save();
                }



                if ($draft && is_null($draft->zone_id)) {
                    $draft->zone_id = $zoneId;
                    $draft->save();
                }

                // If no existing SCR/DraftOrder -> create new
                if (! $scr) {
                    $scr = SubChangeRequest::create([
                        'for_user_id'            => $targetUserId,
                        'by_user_id'             => $user->id,
                        'party_type'             => $partType,
                        'from_id'                => null,
                        'draft_order_id'         => null,
                        'zone_id'                => $zoneId,
                        'subscription_type_id'   => $subscriptionTypeId,
                        'subtypes_json'          => json_encode(['selected_sub_type_ids' => $subTypeIds]),
                        'invoice_cycle'          => 'monthly',
                        'change_reason'          => $targetUserId === (int) $user->id ? 'self_service' : 'operator_assisted',
                        'action'                 => 'create',
                        'status'                 => 'pending',
                        'priority'               => 3,
                        'payload'                => null,
                        'meta'                   => [
                            'source'      => $targetUserId === (int) $user->id ? 'dayli_app' : 'operator_assisted',
                            'for_user_id' => $targetUserId,
                            'by_user_id'  => $user->id,
                            'actor_role'  => $user->getRoleNames()->first(),
                        ],
                    ]);
                }

                if (! $draft) {
                    $draft = DraftOrder::create([
                        'change_request_id'      => $scr->id,
                        'customer_id'            => $targetUser->shopify_customer_id ?? null,
                        'vendor_id'              => null,
                        'zone_id'                => $zoneId,
                        'cadence'                => $groupFrequency,
                        'invoice_cycle'          => 'monthly',
                        'start_date'             => $groupStartDate,
                        'end_date'               => $groupEndDate,
                        'status'                 => 'active',
                        'locked_at'              => null,
                        'timezone'               => 'Asia/Kolkata',
                        'title'                  => 'App selection – ' . $subscriptionTypeId,
                        'meta'                   => [
                            'source'      => $targetUserId === (int) $user->id ? 'dayli_app' : 'operator_assisted',
                            'for_user_id' => $targetUserId,
                            'by_user_id'  => $user->id,
                            'actor_role'  => $user->getRoleNames()->first(),
                        ],
                    ]);

                    $scr->update(['draft_order_id' => $draft->id]);
                }

                /**
                 * ✅ Add draft_order_items into the reused/new DraftOrder
                 * IMPORTANT: this is the part that makes "adding more products in same type/subtype"
                 * not create new SCR again, but still insert new items.
                 */
                foreach ($rows as $row) {
                    $row = (array) $row;

                    $frequency = $row['frequency']  ?? $groupFrequency;
                    $qty       = isset($row['qty']) ? (float) $row['qty'] : 1.0;
                    $unit      = $row['unit']       ?? 'pcs';
                    $startDate = $row['start_date'] ?? $groupStartDate;
                    $endDate   = $row['end_date']   ?? $groupEndDate;
                    if (
                        $request->filled('customer_id') &&
                        $startDate &&
                        Carbon::parse($startDate)->lt(Carbon::today())
                    ) {
                        if ($earliestBackfillDate === null || Carbon::parse($startDate)->lt(Carbon::parse($earliestBackfillDate))) {
                            $earliestBackfillDate = Carbon::parse($startDate)->toDateString();
                        }
                    }

                    DraftOrderItem::create([
                        'draft_order_id' => $draft->id,
                        'product_id'     => (int) $row['product_id'],
                        'variant_id'     => (int) $row['variant_id'],
                        'vendor_id'      => null,

                        'frequency_type' => $frequency,
                        'qty'            => $qty,
                        'unit'           => $unit,
                        'price_snapshot' => $row['price'],
                        'start_date'     => $startDate,
                        'end_date'       => $endDate,

                        'meta'           => [
                            'mrp'              => $row['mrp'] ?? 0,
                            'discount_percent' => $row['discount_percent'] ?? 0,
                            'discount_amount'  => $row['discount_amount'] ?? 0,
                            'cost_price'       => $row['cost_price'] ?? 0,
                        ],
                    ]);
                }

                // return draft object (unique list)
                $draftOrders[] = $draft;
            }

            DB::commit();

            if ($request->filled('customer_id') && $earliestBackfillDate) {
                Artisan::call('dayli:generate-daily-orders', [
                    '--from' => $earliestBackfillDate,
                    '--to' => Carbon::today()->toDateString(),
                    '--customer_id' => $targetUserId,
                ]);
            }

            return response()->json([
                'message'      => 'Selection stored successfully',
                'draft_orders' => $draftOrders,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Error storing selection',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function updateItem(Request $request, DraftOrderItem $item)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Optional safety: ensure this item belongs to this user
        $targetUserId = $this->resolveTargetUserId($request);

        $scr = $item->draftOrder?->changeRequest;
        if ($scr && (int) $scr->for_user_id !== (int) $targetUserId) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate([
            'qty'            => ['required', 'numeric'],
            'unit'           => ['required', 'string', 'max:50'],
            'frequency_type' => ['required', 'string', 'max:50'],
            'start_date'     => ['nullable', 'date'],
            'end_date'       => ['nullable', 'date', 'after_or_equal:start_date'],
            'status'         => ['nullable', 'in:active,paused,cancelled'],
        ]);

        $item->qty            = $data['qty'];
        $item->unit           = $data['unit'];
        $item->frequency_type = $data['frequency_type'];
        $item->start_date     = $data['start_date'] ?? null;
        $item->end_date       = $data['end_date'] ?? null;

        if (isset($data['status'])) {
            $item->status = $data['status'];
        }

        $item->save();

        return response()->json([
            'message' => 'Item updated',
            'item'    => $item->fresh(),
        ], 200);
    }

    private function ensureZoneIdForUser(User $user): ?int
    {
        // If already set, just return it
        if (!is_null($user->zone_id)) {
            return (int) $user->zone_id;
        }

        // Derive from user's pincode
        $pincode = $user->pincode ?? null;
        if (!$pincode) return null;

        $pincode = preg_replace('/\D+/', '', (string)$pincode);
        if (strlen($pincode) !== 6) return null;

        $zone = Zone::findByPinCode($pincode);
        if (!$zone || $zone->status !== 'active') return null;

        // Save once so future calls are cheap
        $user->zone_id = $zone->id;
        $user->save();

        return (int) $zone->id;
    }
    private function resolveTargetUserId(Request $request): int
    {
        $authUser = $request->user();
        if (!$authUser) abort(401, 'Unauthenticated');

        $customerId = $request->input('customer_id') ?: $request->query('customer_id');

        if ($customerId) {
            if (!$authUser->hasAnyRole(['admin', 'zone-manager', 'workman-delivery-boy'])) {
                abort(403, 'Not allowed to manage customer subscriptions');
            }

            return (int) $customerId; // for_user_id
        }

        return (int) $authUser->id;
    }
    
}
