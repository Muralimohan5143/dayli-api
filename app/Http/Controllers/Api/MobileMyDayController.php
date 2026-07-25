<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MyDayFeedItem;
use App\Models\MyDayLike;
use App\Models\MyDayNote;
use App\Models\MyDayRoutine;
use App\Models\MyDayTodo;
use App\Services\MyDayImageService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class MobileMyDayController extends Controller
{
    public function __construct(
        private readonly MyDayImageService $imageService,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $lat = $request->query('lat');
        $lng = $request->query('lng');

        $city = $request->query('city', 'Current Location');
        $today = Carbon::now('Asia/Kolkata');

        $greeting = $this->imageService->greetingImage($today);

        $likes = MyDayLike::where('user_id', $user->id)
            ->where('is_enabled', true)
            ->orderBy('sort_order')
            ->get();

        $feedItems = MyDayFeedItem::where('user_id', $user->id)
            ->where('feed_date', $today->toDateString())
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get();

        $notes = MyDayNote::where('user_id', $user->id)
            ->orderByDesc('is_pinned')
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $todos = MyDayTodo::where('user_id', $user->id)
            ->whereIn('status', ['pending', 'completed'])
            ->orderByRaw("FIELD(status, 'pending', 'completed')")
            ->orderByRaw("FIELD(priority, 'high', 'normal', 'low')")
            ->orderBy('due_date')
            ->limit(20)
            ->get();

        $routines = MyDayRoutine::where('user_id', $user->id)
            ->where('is_active', true)
            ->with(['logs' => function ($q) use ($today) {
                $q->where('log_date', $today->toDateString());
            }])
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'name' => $user->name ?? 'User',
                    'city' => $city,
                    'date_text' => $today->format('l, j F Y'),
                ],

                'greeting' => [
                    'period' => $greeting['period'],
                    'image_path' => $greeting['image_path'],
                    'image_url' => $greeting['image_url'],
                ],

                'likes' => $likes,
                'feed_items' => $feedItems,
                'notes' => $notes,
                'todos' => $todos,
                'routines' => $routines,

                'summary' => [
                    'likes_count' => $likes->count(),
                    'notes_count' => $notes->count(),
                    'pending_todos_count' => $todos
                        ->where('status', 'pending')
                        ->count(),
                    'routines_count' => $routines->count(),
                    'completed_routines_today' => $routines
                        ->filter(function ($routine) {
                            return $routine->logs
                                ->where('status', 'completed')
                                ->count() > 0;
                        })
                        ->count(),
                ],
            ],
        ]);
    }
}
