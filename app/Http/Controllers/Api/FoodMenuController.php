<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoodMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FoodMenuController extends Controller
{
    public function index(Request $request)
    {
        $menus = FoodMenu::with([
            'chef:id,name',
            'product:product_id,title',
            'variant:variant_id,title'
        ])
            ->where('is_active', true)
            ->whereDate('menu_date', now()->toDateString())
            ->latest('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $menus,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_date' => 'required|date',
            'meal_type' => 'required|in:breakfast,lunch,dinner,snacks',

            // NEW
            'product_id' => 'required|exists:products,product_id',
            'variant_id' => 'nullable|exists:variants,variant_id',

            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'is_veg' => 'required|boolean',
            'available_qty' => 'required|integer|min:1',
        ]);

        $menu = FoodMenu::create([
            'chef_id' => Auth::id(),
            'zone_id' => Auth::user()->zone_id ?? 1,
            'menu_date' => $validated['menu_date'],
            'meal_type' => $validated['meal_type'],

            // NEW
            'product_id' => $validated['product_id'],
            'variant_id' => $validated['variant_id'] ?? null,

            'item_name' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'price' => $validated['price'],
            'available_qty' => $validated['available_qty'],
            'is_veg' => $validated['is_veg'],
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu uploaded successfully.',
            'data' => $menu->load([
                'chef:id,name',
                'product:product_id,title',
                'variant:variant_id,title'
            ]),
        ]);
    }

    public function show($id)
    {
        $menu = FoodMenu::with([
            'chef:id,name',
            'product:product_id,title',
            'variant:variant_id,title'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $menu,
        ]);
    }

    public function update(Request $request, $id)
    {
        $menu = FoodMenu::findOrFail($id);

        $menu->update([
            'menu_date' => $request->menu_date,
            'meal_type' => $request->meal_type,

            'product_id' => $request->product_id,
            'variant_id' => $request->variant_id,

            'item_name' => $request->title,
            'description' => $request->description,
            'price' => $request->price,
            'is_veg' => $request->is_veg,
            'available_qty' => $request->available_qty,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Menu updated successfully.',
            'data' => $menu,
        ]);
    }

    public function destroy($id)
    {
        FoodMenu::findOrFail($id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Menu deleted successfully.',
        ]);
    }
    public function getProductsByMealType($mealType)
    {
        $products = FoodMenu::with([
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
            'data' => $products,
        ]);
    }
}
