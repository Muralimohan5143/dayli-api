<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\FoodMenuToday;
use App\Models\FoodPreorder;
use App\Models\FoodMenu;
use Illuminate\Http\Request;

class HomeFoodController extends Controller
{
    public function chefFoodMenus(Request $request)
    {
        $chefId = $request->user()->id;

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

    public function createTodayMenu(Request $request)
    {
        $request->validate([
            'food_menu_id' => 'required|integer',
            'planned_qty' => 'required|integer|min:1',
            'available_qty' => 'nullable|integer|min:0',
            'cutoff_time' => 'nullable',
            'special_note' => 'nullable|string',
        ]);

        $chefId = $request->user()->id;

        $foodMenu = FoodMenu::where('id', $request->food_menu_id)
            ->where('chef_id', $chefId)
            ->firstOrFail();

        $row = FoodMenuToday::create([
            'chef_id' => $chefId,
            'zone_id' => $foodMenu->zone_id,
            'menu_date' => now()->toDateString(),
            'meal_type' => $foodMenu->meal_type,
            'food_menu_id' => $foodMenu->id,
            'product_id' => $foodMenu->product_id,
            'variant_id' => $foodMenu->variant_id,
            'planned_qty' => $request->planned_qty,
            'available_qty' => $request->available_qty ?? $request->planned_qty,
            'cutoff_time' => $request->cutoff_time,
            'status' => 'draft',
            'broadcast_status' => 'not_sent',
            'special_note' => $request->special_note,
            'is_active' => 1,
        ]);

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    public function chefTodayMenu(Request $request)
    {
        $chefId = $request->user()->id;
        $date = $request->date ?? now()->toDateString();

        $items = FoodMenuToday::where('chef_id', $chefId)
            ->where('menu_date', $date)
            ->orderBy('meal_type')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function broadcastTodayMenu($id, Request $request)
    {
        $row = FoodMenuToday::where('id', $id)
            ->where('chef_id', $request->user()->id)
            ->firstOrFail();

        $row->update([
            'broadcast_status' => 'sent',
            'status' => 'broadcasted',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu broadcasted',
            'data' => $row,
        ]);
    }

    public function customerTodayFood(Request $request)
    {
        $request->validate([
            'zone_id' => 'required|integer',
            'meal_type' => 'nullable|string',
        ]);

        $query = FoodMenuToday::where('zone_id', $request->zone_id)
            ->where('menu_date', now()->toDateString())
            ->where('is_active', 1)
            ->whereIn('status', ['broadcasted', 'cooking']);

        if ($request->meal_type) {
            $query->where('meal_type', $request->meal_type);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get(),
        ]);
    }

    public function createPreorder(Request $request)
    {
        $request->validate([
            'food_menu_today_id' => 'required|integer',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $today = FoodMenuToday::findOrFail($request->food_menu_today_id);

        if ($today->available_qty < $request->qty) {
            return response()->json([
                'success' => false,
                'message' => 'Requested qty not available',
            ], 422);
        }

        $preorder = FoodPreorder::create([
            'food_menu_today_id' => $today->id,
            'customer_id' => $request->user()->id,
            'qty' => $request->qty,
            'status' => 'interested',
            'notes' => $request->notes,
        ]);

        return response()->json([
            'success' => true,
            'data' => $preorder,
        ]);
    }

    public function chefPreorders(Request $request)
    {
        $chefId = $request->user()->id;
        $date = $request->date ?? now()->toDateString();

        $todayIds = FoodMenuToday::where('chef_id', $chefId)
            ->where('menu_date', $date)
            ->pluck('id');

        $items = FoodPreorder::whereIn('food_menu_today_id', $todayIds)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }
}
