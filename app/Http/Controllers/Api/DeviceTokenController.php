<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeviceTokenController extends Controller
{
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:20'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'app_version' => ['nullable', 'string', 'max:50'],
        ]);

        DeviceToken::updateOrCreate(
            ['token' => $data['token']],
            [
                'user_id' => $user->id,
                'platform' => $data['platform'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'app_version' => $data['app_version'] ?? null,
                'last_seen_at' => now(),
                'is_valid' => true,
            ]
        );


        // 🔎 DEBUG LOG (ADD THIS BLOCK)
        $row = DeviceToken::where('token', $data['token'])->first();

        Log::info('DEVICE_TOKEN_AFTER_SAVE', [
            'id' => $row?->id,
            'user_id' => $row?->user_id,
            'is_valid_cast' => $row?->is_valid,
            'is_valid_raw' => $row?->getRawOriginal('is_valid'),
            'device_id' => $row?->device_id,
            'platform' => $row?->platform,
        ]);

        return response()->json(['ok' => true]);
    }
}
