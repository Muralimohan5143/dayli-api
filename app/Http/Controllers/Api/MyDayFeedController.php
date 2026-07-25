<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MyDayFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyDayFeedController extends Controller
{
    public function index(
        Request $request,
        MyDayFeedService $service,
    ): JsonResponse {
        $rawKeys = $request->query(
            'keys',
            'gita,health,fun_fact',
        );

        $latitude = $request->filled('lat')
            ? (float) $request->query('lat')
            : null;

        $longitude = $request->filled('lng')
            ? (float) $request->query('lng')
            : null;

        $city = $request->query('city');

        $allowedKeys = [
            'weather',
            'astro',
            'quote',
            'gita',
            'story',
            'movies',
            'music',
            'news',
            'cricket',
            'gold',
            'silver',
            'petrol',
            'diesel',
            'health',
            'recipe',
            'fun_fact',
        ];

        $keys = collect(explode(',', $rawKeys))
            ->map(fn($key) => trim((string) $key))
            ->filter(
                fn($key) =>
                $key !== '' &&
                    in_array($key, $allowedKeys, true)
            )
            ->unique()
            ->values();

        $items = $keys->map(function ($key) use (
            $service,
            $latitude,
            $longitude,
            $city,
        ) {
            try {
                return $service->makeFeedItem(
                    $key,
                    $latitude,
                    $longitude,
                    $city,
                ) + [
                    'interest_key' => $key,
                ];
            } catch (\Throwable $e) {
                report($e);

                return [
                    'interest_key' => $key,
                    'title' => ucfirst(
                        str_replace('_', ' ', $key)
                    ),
                    'subtitle' => 'MyDay update',
                    'body' =>
                    'This update is temporarily unavailable.',
                    'payload_json' => null,
                ];
            }
        })->values();

        return response()->json([
            'success' => true,
            'items' => $items,
        ]);
    }
}
