<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MyDayTodo;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MyDayTodoController extends Controller
{
    public function index(Request $request)
    {
        $todos = MyDayTodo::where('user_id', $request->user()->id)
            ->orderByRaw("FIELD(status, 'pending', 'completed', 'cancelled')")
            ->orderByRaw("FIELD(priority, 'high', 'normal', 'low')")
            ->orderBy('due_date')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $todos,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i',
            'priority' => 'nullable|in:low,normal,high',
            'status' => 'nullable|in:pending,completed,cancelled',
            'remind_at' => 'nullable|date',
            'sort_order' => 'nullable|integer',
        ]);

        $data['user_id'] = $request->user()->id;

        if (($data['status'] ?? 'pending') === 'completed') {
            $data['completed_at'] = Carbon::now();
        }

        $todo = MyDayTodo::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Todo created successfully.',
            'data' => $todo,
        ]);
    }

    public function update(Request $request, $id)
    {
        $todo = MyDayTodo::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i',
            'priority' => 'nullable|in:low,normal,high',
            'status' => 'nullable|in:pending,completed,cancelled',
            'remind_at' => 'nullable|date',
            'sort_order' => 'nullable|integer',
        ]);

        if (isset($data['status'])) {
            if ($data['status'] === 'completed' && $todo->status !== 'completed') {
                $data['completed_at'] = Carbon::now();
            }

            if ($data['status'] !== 'completed') {
                $data['completed_at'] = null;
            }
        }

        $todo->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Todo updated successfully.',
            'data' => $todo,
        ]);
    }

    public function complete(Request $request, $id)
    {
        $todo = MyDayTodo::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $todo->update([
            'status' => 'completed',
            'completed_at' => Carbon::now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Todo completed successfully.',
            'data' => $todo,
        ]);
    }

    public function reopen(Request $request, $id)
    {
        $todo = MyDayTodo::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $todo->update([
            'status' => 'pending',
            'completed_at' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Todo reopened successfully.',
            'data' => $todo,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $todo = MyDayTodo::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $todo->delete();

        return response()->json([
            'success' => true,
            'message' => 'Todo deleted successfully.',
        ]);
    }
}
