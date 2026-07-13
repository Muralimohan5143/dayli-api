<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoodMenu;
use App\Models\FoodMenuToday;
use App\Models\FoodPreorder;
use Illuminate\Http\Request;
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

        $query = FoodMenuToday::where('zone_id', $validated['zone_id'])
            ->whereDate('menu_date', now()->toDateString())
            ->where('is_active', 1)
            ->whereIn('status', ['broadcasted', 'cooking']);

        if (!empty($validated['meal_type'])) {
            $query->where('meal_type', $validated['meal_type']);
        }

        return response()->json([
            'success' => true,
            'data' => $query->latest()->get(),
        ]);
    }

    public function preorder(Request $request)
    {
        $validated = $request->validate([
            'food_menu_today_id' => 'required|exists:food_menu_today,id',
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);

        $today = FoodMenuToday::findOrFail($validated['food_menu_today_id']);

        if ($today->available_qty < $validated['qty']) {
            return response()->json([
                'success' => false,
                'message' => 'Requested qty not available.',
            ], 422);
        }

        $preorder = FoodPreorder::create([
            'food_menu_today_id' => $today->id,
            'customer_id' => Auth::id(),
            'qty' => $validated['qty'],
            'status' => 'interested',
            'notes' => $validated['notes'] ?? null,
        ]);

        $today->decrement('available_qty', $validated['qty']);

        return response()->json([
            'success' => true,
            'message' => 'Preorder saved successfully.',
            'data' => $preorder,
        ]);
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
}
