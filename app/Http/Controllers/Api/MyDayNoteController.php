<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MyDayNote;
use Illuminate\Http\Request;

class MyDayNoteController extends Controller
{
    public function index(Request $request)
    {
        $notes = MyDayNote::where('user_id', $request->user()->id)
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notes,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'is_pinned' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['user_id'] = $request->user()->id;

        $note = MyDayNote::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Note created successfully.',
            'data' => $note,
        ]);
    }

    public function update(Request $request, $id)
    {
        $note = MyDayNote::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'body' => 'nullable|string',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:50',
            'is_pinned' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $note->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Note updated successfully.',
            'data' => $note,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $note = MyDayNote::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $note->delete();

        return response()->json([
            'success' => true,
            'message' => 'Note deleted successfully.',
        ]);
    }
}
