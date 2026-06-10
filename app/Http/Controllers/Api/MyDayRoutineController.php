<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MyDayRoutine;
use App\Models\MyDayRoutineLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class MyDayRoutineController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today()->toDateString();

        $routines = MyDayRoutine::where('user_id', $request->user()->id)
            ->with(['logs' => function ($q) use ($today) {
                $q->where('log_date', $today);
            }])
            ->orderByDesc('is_active')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $routines,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'frequency_type' => 'nullable|in:daily,weekdays,weekends,weekly,custom',
            'days_of_week' => 'nullable|array',
            'time_of_day' => 'nullable|date_format:H:i',
            'remind_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $data['user_id'] = $request->user()->id;

        $routine = MyDayRoutine::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Routine created successfully.',
            'data' => $routine,
        ]);
    }

    public function update(Request $request, $id)
    {
        $routine = MyDayRoutine::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'frequency_type' => 'nullable|in:daily,weekdays,weekends,weekly,custom',
            'days_of_week' => 'nullable|array',
            'time_of_day' => 'nullable|date_format:H:i',
            'remind_at' => 'nullable|date',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $routine->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Routine updated successfully.',
            'data' => $routine,
        ]);
    }

    public function complete(Request $request, $id)
    {
        $userId = $request->user()->id;
        $today = Carbon::today();

        $routine = MyDayRoutine::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        $log = MyDayRoutineLog::updateOrCreate(
            [
                'routine_id' => $routine->id,
                'log_date' => $today->toDateString(),
            ],
            [
                'user_id' => $userId,
                'status' => 'completed',
                'completed_at' => Carbon::now(),
            ]
        );

        $this->recalculateStreak($routine);

        return response()->json([
            'success' => true,
            'message' => 'Routine completed successfully.',
            'data' => [
                'routine' => $routine->fresh(),
                'log' => $log,
            ],
        ]);
    }

    public function skip(Request $request, $id)
    {
        $userId = $request->user()->id;
        $today = Carbon::today();

        $routine = MyDayRoutine::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        $log = MyDayRoutineLog::updateOrCreate(
            [
                'routine_id' => $routine->id,
                'log_date' => $today->toDateString(),
            ],
            [
                'user_id' => $userId,
                'status' => 'skipped',
                'completed_at' => null,
            ]
        );

        $this->recalculateStreak($routine);

        return response()->json([
            'success' => true,
            'message' => 'Routine skipped successfully.',
            'data' => [
                'routine' => $routine->fresh(),
                'log' => $log,
            ],
        ]);
    }

    public function reopen(Request $request, $id)
    {
        $userId = $request->user()->id;
        $today = Carbon::today();

        $routine = MyDayRoutine::where('user_id', $userId)
            ->where('id', $id)
            ->firstOrFail();

        MyDayRoutineLog::where('routine_id', $routine->id)
            ->where('user_id', $userId)
            ->where('log_date', $today->toDateString())
            ->delete();

        $this->recalculateStreak($routine);

        return response()->json([
            'success' => true,
            'message' => 'Routine reopened successfully.',
            'data' => $routine->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $routine = MyDayRoutine::where('user_id', $request->user()->id)
            ->where('id', $id)
            ->firstOrFail();

        $routine->delete();

        return response()->json([
            'success' => true,
            'message' => 'Routine deleted successfully.',
        ]);
    }

    private function recalculateStreak(MyDayRoutine $routine): void
    {
        $today = Carbon::today();
        $streak = 0;

        for ($i = 0; $i < 365; $i++) {
            $date = $today->copy()->subDays($i)->toDateString();

            $completed = MyDayRoutineLog::where('routine_id', $routine->id)
                ->where('log_date', $date)
                ->where('status', 'completed')
                ->exists();

            if ($completed) {
                $streak++;
            } else {
                break;
            }
        }

        $routine->current_streak = $streak;
        $routine->best_streak = max($routine->best_streak, $streak);
        $routine->save();
    }
}
