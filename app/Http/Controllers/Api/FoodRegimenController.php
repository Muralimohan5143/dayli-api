<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FoodRegimen;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FoodRegimenController extends Controller
{
    public function index(Request $request)
    {
        $regimens = FoodRegimen::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('day_of_week')
            ->orderBy('meal_type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $regimens,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'meal_type' => 'required|in:breakfast,lunch,dinner',
            'preference' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['user_id'] = Auth::id();

        $regimen = FoodRegimen::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Food regimen saved successfully.',
            'data' => $regimen,
        ]);
    }

    public function show($id)
    {
        $regimen = FoodRegimen::where('user_id', Auth::id())
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $regimen,
        ]);
    }

    public function update(Request $request, $id)
    {
        $regimen = FoodRegimen::where('user_id', Auth::id())
            ->findOrFail($id);

        $validated = $request->validate([
            'day_of_week' => 'nullable|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'meal_type' => 'nullable|in:breakfast,lunch,dinner',
            'preference' => 'nullable|string',
            'notes' => 'nullable|string',
            'is_active' => 'nullable|boolean',
        ]);

        $regimen->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Food regimen updated successfully.',
            'data' => $regimen,
        ]);
    }

    public function destroy($id)
    {
        $regimen = FoodRegimen::where('user_id', Auth::id())
            ->findOrFail($id);

        $regimen->update([
            'is_active' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Food regimen removed successfully.',
        ]);
    }
}
