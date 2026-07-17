<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPushToUserJob;
use App\Models\FoodMenu;
use App\Models\FoodMenuToday;
use App\Models\FoodPreorder;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class FoodMenuTodayController extends Controller
{
    public function chefFoodMenus()
    {
        $chefId = Auth::id();

        $items = FoodMenu::where('chef_id', $chefId)
            ->where('is_active', 1)
            ->orderBy('meal_type')
            ->orderBy('item_name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'food_menu_id' => 'required|exists:food_menus,id',
            'planned_qty' => 'required|integer|min:1',
            'available_qty' => 'nullable|integer|min:0',
            'cutoff_time' => 'nullable',
            'special_note' => 'nullable|string',
        ]);

        $chefId = Auth::id();

        $foodMenu = FoodMenu::where('id', $validated['food_menu_id'])
            ->where('chef_id', $chefId)
            ->firstOrFail();

        $today = FoodMenuToday::updateOrCreate(
            [
                'chef_id' => $chefId,
                'menu_date' => now()->toDateString(),
                'food_menu_id' => $foodMenu->id,
            ],
            [
                'zone_id' => $foodMenu->zone_id,
                'meal_type' => $foodMenu->meal_type,
                'product_id' => $foodMenu->product_id,
                'variant_id' => $foodMenu->variant_id,
                'planned_qty' => $validated['planned_qty'],
                'available_qty' => $validated['available_qty'] ?? $validated['planned_qty'],
                'cutoff_time' => $validated['cutoff_time'] ?? null,
                'special_note' => $validated['special_note'] ?? null,
                'broadcast_status' => 'not_sent',
                'status' => 'draft',
                'is_active' => 1,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Today menu saved successfully.',
            'data' => $today,
        ]);
    }

    public function index(Request $request)
    {
        $chefId = Auth::id();
        $date = $request->date ?? now()->toDateString();

        $items = FoodMenuToday::query()
            ->leftJoin('food_menus', 'food_menu_today.food_menu_id', '=', 'food_menus.id')
            ->select(
                'food_menu_today.*',
                'food_menus.item_name',
                'food_menus.price'
            )
            ->where('food_menu_today.chef_id', $chefId)
            ->whereDate('food_menu_today.menu_date', $date)
            ->orderBy('food_menu_today.meal_type')
            ->orderByDesc('food_menu_today.id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function broadcast($id)
    {
        $today = FoodMenuToday::where('id', $id)
            ->where('chef_id', Auth::id())
            ->firstOrFail();

        $today->update([
            'broadcast_status' => 'sent',
            'status' => 'broadcasted',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Today menu broadcasted successfully.',
            'data' => $today,
        ]);
    }



    public function customerTodayFood(Request $request)
    {
        $validated = $request->validate([
            'zone_id' => 'required|integer',
            'meal_type' => 'nullable|in:breakfast,lunch,dinner,snacks',
        ]);

        $query = FoodMenuToday::query()
            ->leftJoin(
                'food_menus',
                'food_menu_today.food_menu_id',
                '=',
                'food_menus.id'
            )
            ->leftJoin(
                'users',
                'food_menu_today.chef_id',
                '=',
                'users.id'
            )
            ->select(
                'food_menu_today.id as food_menu_today_id',
                'food_menu_today.chef_id',
                'food_menu_today.zone_id',
                'food_menu_today.meal_type',
                'food_menu_today.food_menu_id',
                'food_menu_today.product_id',
                'food_menu_today.variant_id',
                'food_menu_today.planned_qty',
                'food_menu_today.available_qty',
                'food_menu_today.cutoff_time',
                'food_menu_today.special_note',
                'food_menu_today.broadcast_status',
                'food_menu_today.status',
                'food_menu_today.menu_date',
                'food_menus.item_name',
                'food_menus.price',
                'users.name as chef_name'
            )
            ->where('food_menu_today.zone_id', $validated['zone_id'])
            ->whereDate('food_menu_today.menu_date', now()->toDateString())
            ->where('food_menu_today.is_active', 1)
            ->whereIn('food_menu_today.status', ['broadcasted', 'cooking']);

        if (!empty($validated['meal_type'])) {
            $query->where(
                'food_menu_today.meal_type',
                $validated['meal_type']
            );
        }

        $rows = $query
            ->orderBy('food_menu_today.meal_type')
            ->orderByDesc('food_menu_today.id')
            ->get();

        $items = $rows
            ->groupBy(function ($row) {
                return implode('|', [
                    (string) $row->product_id,
                    (string) $row->variant_id,
                    (string) $row->meal_type,
                ]);
            })
            ->map(function ($group) {
                $first = $group->first();

                $offers = $group
                    ->map(function ($row) {
                        return [
                            'food_menu_today_id' => (int) $row->food_menu_today_id,
                            'chef_id' => (int) $row->chef_id,
                            'chef_name' => $row->chef_name
                                ?: 'Home Chef #' . $row->chef_id,
                            'price' => (float) $row->price,
                            'available_qty' => (int) $row->available_qty,
                            'planned_qty' => (int) $row->planned_qty,
                            'cutoff_time' => $row->cutoff_time,
                            'special_note' => $row->special_note,
                            'status' => $row->status,
                        ];
                    })
                    ->sortBy([
                        ['price', 'asc'],
                        ['available_qty', 'desc'],
                    ])
                    ->values();

                return [
                    'product_id' => (int) $first->product_id,
                    'variant_id' => (int) $first->variant_id,
                    'meal_type' => $first->meal_type,
                    'item_name' => $first->item_name,
                    'min_price' => (float) $offers->min('price'),
                    'max_price' => (float) $offers->max('price'),
                    'total_available_qty' => (int) $offers->sum('available_qty'),
                    'vendor_count' => $offers
                        ->pluck('chef_id')
                        ->unique()
                        ->count(),
                    'offers' => $offers,
                ];
            })
            ->sortBy([
                ['meal_type', 'asc'],
                ['min_price', 'asc'],
                ['item_name', 'asc'],
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function preorder(Request $request)
    {
        $validated = $request->validate([
            'food_menu_today_id' => 'required|integer|exists:food_menu_today,id',
            'qty' => 'required|integer|min:1|max:100',
            'notes' => 'nullable|string|max:1000',
        ]);

        $customerId = (int) Auth::id();

        $result = DB::transaction(function () use ($validated, $customerId) {
            /*
         * Lock this menu row until the transaction completes.
         *
         * This prevents two customers from purchasing the same
         * last available plates at exactly the same time.
         */
            $today = FoodMenuToday::query()
                ->where('id', $validated['food_menu_today_id'])
                ->lockForUpdate()
                ->firstOrFail();

            if (!$today->is_active) {
                throw ValidationException::withMessages([
                    'food_menu_today_id' => 'This food item is no longer active.',
                ]);
            }

            if (!in_array($today->status, ['broadcasted', 'cooking'], true)) {
                throw ValidationException::withMessages([
                    'food_menu_today_id' => 'This food item is not currently available for ordering.',
                ]);
            }

            if ($today->menu_date->toDateString() !== now()->toDateString()) {
                throw ValidationException::withMessages([
                    'food_menu_today_id' => 'This menu item is not available today.',
                ]);
            }

            if (
                $today->cutoff_time &&
                now()->format('H:i:s') > $today->cutoff_time
            ) {
                throw ValidationException::withMessages([
                    'food_menu_today_id' => 'The ordering cutoff time has passed.',
                ]);
            }

            $qty = (int) $validated['qty'];

            if ((int) $today->available_qty < $qty) {
                throw ValidationException::withMessages([
                    'qty' => "Only {$today->available_qty} plate(s) are available.",
                ]);
            }

            $foodMenu = FoodMenu::query()
                ->where('id', $today->food_menu_id)
                ->where('chef_id', $today->chef_id)
                ->firstOrFail();

            $unitPrice = round((float) $foodMenu->price, 2);
            $subtotal = round($unitPrice * $qty, 2);

            /*
         * Create the real Dayli order.
         */
            $order = Order::create([
                'order_type' => 'on_demand',

                'customer_id' => $customerId,
                'vendor_id' => (int) $today->chef_id,
                'zone_id' => (int) $today->zone_id,

                'delivery_date' => $today->menu_date,
                'delivery_status' => 'pending',

                /*
             * Chef must accept the order in Phase 3.
             */
                'status' => 'pending',
                'confirmed' => 0,
                'closed' => 0,

                'requires_shipping' => 1,
                'unpaid' => 1,

                'item_count' => $qty,
                'currency' => 'INR',
                'currency_code' => 'INR',

                'subtotal' => $subtotal,
                'tax' => 0,
                'discount' => 0,
                'total' => $subtotal,

                'current_subtotal' => $subtotal,
                'current_tax' => 0,
                'current_discounts' => 0,
                'current_shipping' => 0,
                'current_total' => $subtotal,

                'financial_status' => 'pending',
                'fulfillment_status' => 'unfulfilled',

                'source_name' => 'dayli_home_food',
                'note' => $validated['notes'] ?? null,

                'meta' => [
                    'source' => 'home_food',
                    'food_menu_today_id' => (int) $today->id,
                    'food_menu_id' => (int) $today->food_menu_id,
                    'chef_id' => (int) $today->chef_id,
                    'meal_type' => $today->meal_type,
                    'menu_date' => $today->menu_date->toDateString(),
                    'special_note' => $today->special_note,
                    'order_flow' => 'first_time_order',
                ],
            ]);

            /*
         * Create the order line item.
         */
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $today->product_id,
                'variant_id' => $today->variant_id,

                'title' => $foodMenu->item_name,
                'variant' => $today->meal_type,

                'quantity' => $qty,
                'unit_price' => $unitPrice,
                'line_total' => $subtotal,

                'actuals_date' => $today->menu_date,

                'meta' => [
                    'source' => 'home_food',
                    'food_menu_today_id' => (int) $today->id,
                    'food_menu_id' => (int) $today->food_menu_id,
                    'chef_id' => (int) $today->chef_id,
                    'meal_type' => $today->meal_type,
                ],
            ]);

            /*
         * Keep food_preorders as the Home Food reservation/audit row,
         * but mark it as already converted into a real order.
         */
            $preorder = FoodPreorder::create([
                'food_menu_today_id' => $today->id,
                'customer_id' => $customerId,
                'qty' => $qty,
                'status' => 'converted_to_order',
                'notes' => $validated['notes'] ?? null,
            ]);

            /*
         * Safely reduce available plates.
         */
            $today->decrement('available_qty', $qty);
            $today->refresh();

            return [
                'order' => $order,
                'order_item' => $orderItem,
                'preorder' => $preorder,
                'remaining_qty' => (int) $today->available_qty,
            ];
        }, 3);

        SendPushToUserJob::dispatch(
            (int) $result['order']->vendor_id,
            [
                'title' => 'New Home Food Order',
                'body' => "{$result['order_item']->quantity} plate(s) of {$result['order_item']->title} ordered.",
                'data' => [
                    'type' => 'home_food_order_created',
                    'screen' => 'chef_food_preorders',
                    'order_id' => (string) $result['order']->id,
                    'food_menu_today_id' => (string) $result['preorder']->food_menu_today_id,
                    'customer_id' => (string) $result['order']->customer_id,
                    'qty' => (string) $result['order_item']->quantity,
                    'item_name' => (string) $result['order_item']->title,
                    'total' => (string) $result['order']->total,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Home Food order placed successfully.',
            'data' => [
                'order_id' => $result['order']->id,
                'status' => $result['order']->status,
                'customer_id' => $result['order']->customer_id,
                'chef_id' => $result['order']->vendor_id,
                'food_menu_today_id' => $result['preorder']->food_menu_today_id,
                'item_name' => $result['order_item']->title,
                'qty' => $result['order_item']->quantity,
                'unit_price' => (float) $result['order_item']->unit_price,
                'total' => (float) $result['order']->total,
                'remaining_qty' => $result['remaining_qty'],
            ],
        ], 201);
    }
    public function preorders(Request $request)
    {
        $chefId = Auth::id();
        $date = $request->date ?? now()->toDateString();

        $todayIds = FoodMenuToday::where('chef_id', $chefId)
            ->whereDate('menu_date', $date)
            ->pluck('id');

        $items = FoodPreorder::whereIn('food_menu_today_id', $todayIds)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function update(Request $request, $id)
    {
        $chefId = Auth::id();

        $row = FoodMenuToday::where('id', $id)
            ->where('chef_id', $chefId)
            ->whereDate('menu_date', now()->toDateString())
            ->firstOrFail();

        if ($row->status === 'broadcasted') {
            return response()->json([
                'success' => false,
                'message' => 'Broadcasted menu cannot be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'planned_qty' => 'required|integer|min:1',
            'available_qty' => 'required|integer|min:0',
            'cutoff_time' => 'nullable',
            'special_note' => 'nullable|string',
        ]);

        $row->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Today menu item updated.',
            'data' => $row,
        ]);
    }

    public function destroy($id)
    {
        $chefId = Auth::id();

        $row = FoodMenuToday::where('id', $id)
            ->where('chef_id', $chefId)
            ->whereDate('menu_date', now()->toDateString())
            ->firstOrFail();

        if ($row->status === 'broadcasted') {
            return response()->json([
                'success' => false,
                'message' => 'Broadcasted menu cannot be deleted.',
            ], 422);
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Today menu item deleted.',
        ]);
    }

    public function broadcastAllToday()
    {
        $chefId = Auth::id();

        $count = FoodMenuToday::where('chef_id', $chefId)
            ->whereDate('menu_date', now()->toDateString())
            ->where('is_active', 1)
            ->update([
                'broadcast_status' => 'sent',
                'status' => 'broadcasted',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Today menu broadcasted.',
            'count' => $count,
        ]);
    }
    public function chefHomeFoodOrders(Request $request)
    {
        $chefId = (int) Auth::id();

        $validated = $request->validate([
            'status' => 'nullable|in:pending,confirmed,cancelled,fulfilled',
            'date' => 'nullable|date',
        ]);

        $query = Order::query()
            ->with('items')
            ->where('vendor_id', $chefId)
            ->where('source_name', 'dayli_home_food')
            ->where('order_type', 'on_demand');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (!empty($validated['date'])) {
            $query->whereDate('delivery_date', $validated['date']);
        } else {
            $query->whereDate('delivery_date', now()->toDateString());
        }

        $orders = $query
            ->latest('id')
            ->get()
            ->map(function (Order $order) {
                $item = $order->items->first();

                return [
                    'order_id' => (int) $order->id,
                    'customer_id' => (int) $order->customer_id,
                    'chef_id' => (int) $order->vendor_id,
                    'zone_id' => $order->zone_id
                        ? (int) $order->zone_id
                        : null,

                    'status' => $order->status,
                    'confirmed' => (bool) $order->confirmed,
                    'cancelled' => (bool) $order->cancelled,

                    'cancel_reason' => $order->cancel_reason,
                    'cancelled_at' => optional($order->cancelled_at)
                        ? Carbon::parse($order->cancelled_at)->toIso8601String()
                        : null,

                    'delivery_date' => optional($order->delivery_date)
                        ? Carbon::parse($order->delivery_date)->toDateString()
                        : null,

                    'meal_type' => data_get($order->meta, 'meal_type'),
                    'food_menu_today_id' => data_get(
                        $order->meta,
                        'food_menu_today_id'
                    ),

                    'item_name' => $item?->title,
                    'qty' => $item ? (int) $item->quantity : 0,
                    'unit_price' => $item
                        ? (float) $item->unit_price
                        : 0,

                    'total' => (float) $order->total,
                    'notes' => $order->note,

                    'created_at' => optional($order->created_at)
                        ? $order->created_at->toIso8601String()
                        : null,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }
    public function acceptHomeFoodOrder($id)
    {
        $chefId = (int) Auth::id();

        $result = DB::transaction(function () use ($id, $chefId) {
            $order = Order::query()
                ->with('items')
                ->where('id', $id)
                ->where('vendor_id', $chefId)
                ->where('source_name', 'dayli_home_food')
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === 'confirmed') {
                return [
                    'order' => $order,
                    'already_processed' => true,
                ];
            }

            if ($order->status === 'cancelled' || $order->cancelled) {
                throw ValidationException::withMessages([
                    'order_id' => 'Cancelled order cannot be accepted.',
                ]);
            }

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'order_id' => "Order cannot be accepted from {$order->status} status.",
                ]);
            }

            $order->update([
                'status' => 'confirmed',
                'confirmed' => 1,
                'cancelled' => 0,
                'cancelled_at' => null,
                'cancel_reason' => null,
                'cancel_reason_label' => null,
                'fulfillment_status' => 'unfulfilled',
                'display_fulfillment_status' => 'Unfulfilled',
            ]);

            $foodMenuTodayId = data_get(
                $order->meta,
                'food_menu_today_id'
            );

            if ($foodMenuTodayId) {
                FoodPreorder::query()
                    ->where('food_menu_today_id', $foodMenuTodayId)
                    ->where('customer_id', $order->customer_id)
                    ->where('status', 'converted_to_order')
                    ->latest('id')
                    ->limit(1)
                    ->update([
                        'status' => 'confirmed',
                    ]);
            }

            return [
                'order' => $order->fresh('items'),
                'already_processed' => false,
            ];
        }, 3);

        $order = $result['order'];
        $item = $order->items->first();

        if (!$result['already_processed']) {
            SendPushToUserJob::dispatch(
                (int) $order->customer_id,
                [
                    'title' => 'Home Food Order Accepted',
                    'body' => "Your order for {$item?->title} has been accepted by the chef.",
                    'data' => [
                        'type' => 'home_food_order_accepted',
                        'screen' => 'customer_home_food_order',
                        'order_id' => (string) $order->id,
                        'chef_id' => (string) $order->vendor_id,
                        'status' => 'confirmed',
                        'item_name' => (string) ($item?->title ?? ''),
                        'qty' => (string) ($item?->quantity ?? 0),
                    ],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => $result['already_processed']
                ? 'Order was already accepted.'
                : 'Order accepted successfully.',
            'data' => [
                'order_id' => (int) $order->id,
                'status' => $order->status,
                'confirmed' => (bool) $order->confirmed,
                'cancelled' => (bool) $order->cancelled,
            ],
        ]);
    }
    public function rejectHomeFoodOrder(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $chefId = (int) Auth::id();

        $result = DB::transaction(function () use (
            $id,
            $chefId,
            $validated
        ) {
            $order = Order::query()
                ->with('items')
                ->where('id', $id)
                ->where('vendor_id', $chefId)
                ->where('source_name', 'dayli_home_food')
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === 'cancelled' || $order->cancelled) {
                return [
                    'order' => $order,
                    'already_processed' => true,
                ];
            }

            if ($order->status === 'confirmed' || $order->confirmed) {
                throw ValidationException::withMessages([
                    'order_id' => 'Accepted order cannot be rejected.',
                ]);
            }

            if ($order->status !== 'pending') {
                throw ValidationException::withMessages([
                    'order_id' => "Order cannot be rejected from {$order->status} status.",
                ]);
            }

            $item = $order->items->first();

            if (!$item) {
                throw ValidationException::withMessages([
                    'order_id' => 'Order item was not found.',
                ]);
            }

            $foodMenuTodayId = data_get(
                $order->meta,
                'food_menu_today_id'
            );

            if (!$foodMenuTodayId) {
                throw ValidationException::withMessages([
                    'order_id' => 'Home Food menu reference was not found.',
                ]);
            }

            /*
         * Lock menu row before returning the reserved quantity.
         */
            $today = FoodMenuToday::query()
                ->where('id', $foodMenuTodayId)
                ->lockForUpdate()
                ->first();

            if ($today) {
                $today->increment(
                    'available_qty',
                    (int) $item->quantity
                );
            }

            $order->update([
                'status' => 'cancelled',
                'confirmed' => 0,
                'cancelled' => 1,
                'cancelled_at' => now(),
                'cancel_reason' => $validated['reason'],
                'cancel_reason_label' => $validated['reason'],
                'closed' => 1,
                'fulfillment_status' => null,
                'display_fulfillment_status' => 'Cancelled',
            ]);

            FoodPreorder::query()
                ->where('food_menu_today_id', $foodMenuTodayId)
                ->where('customer_id', $order->customer_id)
                ->whereIn('status', [
                    'converted_to_order',
                    'confirmed',
                ])
                ->latest('id')
                ->limit(1)
                ->update([
                    'status' => 'cancelled',
                ]);

            return [
                'order' => $order->fresh('items'),
                'already_processed' => false,
            ];
        }, 3);

        $order = $result['order'];
        $item = $order->items->first();

        if (!$result['already_processed']) {
            SendPushToUserJob::dispatch(
                (int) $order->customer_id,
                [
                    'title' => 'Home Food Order Rejected',
                    'body' => "Your order for {$item?->title} could not be accepted.",
                    'data' => [
                        'type' => 'home_food_order_rejected',
                        'screen' => 'customer_home_food_order',
                        'order_id' => (string) $order->id,
                        'chef_id' => (string) $order->vendor_id,
                        'status' => 'cancelled',
                        'reason' => (string) $order->cancel_reason,
                        'item_name' => (string) ($item?->title ?? ''),
                        'qty' => (string) ($item?->quantity ?? 0),
                    ],
                ]
            );
        }

        return response()->json([
            'success' => true,
            'message' => $result['already_processed']
                ? 'Order was already rejected.'
                : 'Order rejected successfully.',
            'data' => [
                'order_id' => (int) $order->id,
                'status' => $order->status,
                'confirmed' => (bool) $order->confirmed,
                'cancelled' => (bool) $order->cancelled,
                'cancel_reason' => $order->cancel_reason,
            ],
        ]);
    }
}
