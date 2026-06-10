<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MyDayFeedItem;
use App\Models\MyDayLike;
use App\Services\MyDayFeedService;
use Illuminate\Http\Request;

class MyDayLikeController extends Controller
{
    public function options()
    {
        return response()->json([
            'success' => true,
            'data' => $this->availableLikes(),
        ]);
    }

    public function index(Request $request)
    {
        $likes = MyDayLike::where('user_id', $request->user()->id)
            ->orderBy('sort_order')
            ->orderBy('interest_title')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $likes,
        ]);
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'likes' => 'required|array',
            'likes.*' => 'required|string|max:50',
        ]);

        $userId = $request->user()->id;
        $options = collect($this->availableLikes())->keyBy('key');

        MyDayLike::where('user_id', $userId)->delete();

        foreach ($data['likes'] as $index => $key) {
            if (!$options->has($key)) {
                continue;
            }

            $option = $options[$key];

            MyDayLike::create([
                'user_id' => $userId,
                'interest_key' => $key,
                'interest_title' => $option['title'],
                'category' => $option['category'],
                'is_enabled' => true,
                'sort_order' => $index + 1,
            ]);
        }

        $this->generateTodayFeed($userId, $data['likes']);

        return response()->json([
            'success' => true,
            'message' => 'Likes saved successfully.',
        ]);
    }

    private function generateTodayFeed(int $userId, array $likes): void
    {
        $today = now('Asia/Kolkata')->toDateString();
        $feedService = app(MyDayFeedService::class);

        foreach ($likes as $index => $key) {
            $item = $feedService->makeFeedItem($key);

            MyDayFeedItem::updateOrCreate(
                [
                    'user_id' => $userId,
                    'interest_key' => $key,
                    'feed_date' => $today,
                ],
                [
                    'title' => $item['title'],
                    'subtitle' => $item['subtitle'],
                    'body' => $item['body'],
                    'payload_json' => $item['payload_json'],
                    'sort_order' => $index + 1,
                    'is_read' => false,
                ]
            );
        }
    }

    private function availableLikes(): array
    {
        return [
            ['key' => 'weather', 'title' => 'Weather', 'category' => 'life', 'icon' => 'cloud'],
            ['key' => 'astro', 'title' => 'Astro / Panchang', 'category' => 'life', 'icon' => 'temple_hindu'],
            ['key' => 'quote', 'title' => 'Quote of the Day', 'category' => 'knowledge', 'icon' => 'format_quote'],
            ['key' => 'gita', 'title' => 'Gita Verse', 'category' => 'spiritual', 'icon' => 'menu_book'],
            ['key' => 'story', 'title' => 'Daily Story', 'category' => 'spiritual', 'icon' => 'auto_stories'],
            ['key' => 'movies', 'title' => 'Movies & OTT', 'category' => 'entertainment', 'icon' => 'movie'],
            ['key' => 'music', 'title' => 'New Music', 'category' => 'entertainment', 'icon' => 'music_note'],
            ['key' => 'news', 'title' => 'News', 'category' => 'news', 'icon' => 'newspaper'],
            ['key' => 'cricket', 'title' => 'Cricket', 'category' => 'news', 'icon' => 'sports_cricket'],
            ['key' => 'gold', 'title' => 'Gold Price', 'category' => 'finance', 'icon' => 'paid'],
            ['key' => 'silver', 'title' => 'Silver Price', 'category' => 'finance', 'icon' => 'toll'],
            ['key' => 'petrol', 'title' => 'Petrol Price', 'category' => 'finance', 'icon' => 'local_gas_station'],
            ['key' => 'health', 'title' => 'Health Tip', 'category' => 'health', 'icon' => 'favorite'],
            ['key' => 'recipe', 'title' => 'Recipe', 'category' => 'health', 'icon' => 'restaurant'],
            ['key' => 'fun_fact', 'title' => 'Fun Fact', 'category' => 'knowledge', 'icon' => 'lightbulb'],
        ];
    }
}
