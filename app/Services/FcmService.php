<?php

namespace App\Services;

use App\Models\DeviceToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class FcmService
{
    private Client $http;

    public function __construct()
    {
        $this->http = new Client(['timeout' => 10]);
    }

    private function accessToken(): string
    {
        $jsonPath = config('services.fcm.service_account_json');
        $scopes = ['https://www.googleapis.com/auth/firebase.messaging'];

        $creds = new ServiceAccountCredentials($scopes, $jsonPath);
        $tokenArr = $creds->fetchAuthToken();

        if (!isset($tokenArr['access_token'])) {
            throw new \RuntimeException('FCM access_token not generated');
        }
        return $tokenArr['access_token'];
    }

    public function sendToToken(string $token, array $payload): array
    {
        $projectId = config('services.fcm.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $notificationId = $payload['notification_id'] ?? (string) Str::uuid();

        $body = [
            'message' => [
                'token' => $token,

                // Show in tray (optional but typical)
                'notification' => [
                    'title' => (string)($payload['title'] ?? ''),
                    'body' => (string)($payload['body'] ?? ''),
                ],

                // For app routing
                'data' => array_map('strval', array_merge([
                    'notification_id' => $notificationId,
                    'ts' => (string) time(),
                ], $payload['data'] ?? [])),
            ],
        ];

        $res = $this->http->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken(),
                'Content-Type' => 'application/json',
            ],
            'json' => $body,
        ]);

        return json_decode((string)$res->getBody(), true) ?? [];
    }

    public function sendToUser(int $userId, array $payload): array
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $userId)
            ->where('is_valid', true)
            ->pluck('token')
            ->all();

        $ok = 0;
        $fail = 0;

        foreach ($tokens as $t) {
            try {
                $this->sendToToken($t, $payload);
                $ok++;
            } catch (\Throwable $e) {
                $fail++;

                // If token invalid, mark it (FCM returns 404/400 in some cases)
                // Keep it simple: mark invalid on any 404-like message.
                // You can improve by parsing $e->getMessage() / response body.
                DeviceToken::where('token', $t)->update(['is_valid' => false]);
            }
        }

        return ['ok' => $ok, 'fail' => $fail, 'total' => count($tokens)];
    }
}
