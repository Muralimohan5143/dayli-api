<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileAdController extends Controller
{
    /**
     * Return one active ad for the requested placement.
     *
     * GET /api/mobile/ads?placement=myday_likes
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'placement' => [
                'required',
                'string',
                'in:myday_likes,shop_top,services_top',
            ],
        ]);

        $ad = Ad::query()
            ->currentlyActive()
            ->where('placement', $validated['placement'])
            ->orderByDesc('priority')
            ->orderByDesc('id')
            ->first();

        if (!$ad) {
            return response()->json([
                'ok' => true,
                'data' => null,
            ]);
        }

        return response()->json([
            'ok' => true,
            'data' => [
                'id' => $ad->id,
                'title' => $ad->title,
                'subtitle' => $ad->subtitle,
                'image_url' => $ad->image_url,
                'placement' => $ad->placement,
                'action_type' => $ad->action_type,
                'action_value' => $ad->action_value,
                'button_text' => $ad->button_text,
            ],
        ]);
    }

    /**
     * POST /api/mobile/ads/{ad}/impression
     */
    public function impression(Ad $ad): JsonResponse
    {
        $ad->increment('impressions_count');

        return response()->json([
            'ok' => true,
        ]);
    }

    /**
     * POST /api/mobile/ads/{ad}/click
     */
    public function click(Ad $ad): JsonResponse
    {
        $ad->increment('clicks_count');

        return response()->json([
            'ok' => true,
        ]);
    }
}
