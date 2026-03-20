<?php

namespace App\Jobs;

use App\Models\DeviceToken;
use App\Services\FcmService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendPushToUserJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [5, 30, 120];

    public function __construct(
        public int $userId,
        public array $payload
    ) {}

    public function handle(FcmService $fcm): void
    {

        // ✅ SAVE notification in DB
        DB::table('notifications')->insert([
            'user_id' => $this->userId,
            'title'   => $this->payload['title'] ?? 'Notification',
            'body'    => $this->payload['body'] ?? null,

            // ✅ FIXED
            'data'    => json_encode($this->payload['data'] ?? []),

            // ✅ FIXED
            'type'    => $this->payload['data']['type'] ?? null,

            'source'  => 'system',
            'is_read' => 0,

            'created_at' => now(),
            'updated_at' => now(),
        ]);
        // 1) Check tokens BEFORE sending
        $tokens = DeviceToken::query()
            ->where('user_id', $this->userId)
            ->where('is_valid', 1)
            ->orderByDesc('id')
            ->pluck('token')
            ->all();

        Log::info('PUSH_DEBUG_TOKENS', [
            'to_user' => $this->userId,
            'valid_tokens_count' => count($tokens),
            'latest_token_prefix' => $tokens ? substr($tokens[0], 0, 25) : null,
            'payload_title' => $this->payload['title'] ?? null,
        ]);

        // 2) Send and log response
        try {
            $res = $fcm->sendToTokens($tokens, $this->payload);

            Log::info('PUSH_DEBUG_FCM_RESPONSE', [
                'to_user' => $this->userId,
                'response' => $res,
            ]);
        } catch (\Throwable $e) {
            Log::error('PUSH_DEBUG_EXCEPTION', [
                'to_user' => $this->userId,
                'msg' => $e->getMessage(),
            ]);

            throw $e; // keep retry behavior
        }
    }
}
