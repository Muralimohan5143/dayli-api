<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoodMenu;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class HomeFoodOrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'food_menu_id' => 'required|exists:food_menus,id',
            'qty' => 'required|integer|min:1',
        ]);

        DB::beginTransaction();

        try {
            $menu = FoodMenu::findOrFail($request->food_menu_id);

            $qty = (int) $request->qty;
            $total = $menu->price * $qty;

            $order = Order::create([
                'customer_id' => Auth::id(),
                'vendor_id' => $menu->chef_id,
                'zone_id' => $menu->zone_id,

                'order_type' => 'on_demand',
                'source_name' => 'home_food',

                'delivery_date' => now()->toDateString(),
                'delivery_status' => 'pending',
                'status' => 'pending',

                'item_count' => $qty,

                'subtotal' => $total,
                'total' => $total,
                'current_subtotal' => $total,
                'current_total' => $total,

                'currency_code' => 'INR',

                'meta' => [
                    'source' => 'home_food',
                    'food_menu_id' => $menu->id,
                    'meal_type' => $menu->meal_type,
                    'chef_id' => $menu->chef_id,
                ],
            ]);

            OrderItem::create([
                'order_id' => $order->id,

                'title' => $menu->item_name,
                'quantity' => $qty,
                'unit_price' => $menu->price,
                'line_total' => $total,

                'actuals_date' => now()->toDateString(),

                'meta' => [
                    'source' => 'home_food',
                    'food_menu_id' => $menu->id,
                    'meal_type' => $menu->meal_type,
                    'chef_id' => $menu->chef_id,
                ],
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order placed successfully',
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function chefOrders(Request $request)
    {
        $orders = Order::with(['items'])
            ->where('vendor_id', Auth::id())
            ->where('source_name', 'home_food')
            ->whereDate('delivery_date', now()->toDateString())
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'orders' => $orders,
        ]);
    }

    public function getProductsByMealType($mealType)
    {
        $menus = FoodMenu::with([
            'chef:id,name',
            'product:product_id,title',
            'variant:variant_id,title'
        ])
            ->where('meal_type', $mealType)
            ->whereDate('menu_date', now()->toDateString())
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $menus
        ]);
    }

    public function getChefMenus($productId)
    {
        $menus = FoodMenu::with([
            'chef:id,name',
            'variant:variant_id,title'
        ])
            ->where('product_id', $productId)
            ->whereDate('menu_date', now()->toDateString())
            ->where('is_active', true)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $menus
        ]);
    }
}
