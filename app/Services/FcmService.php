<?php

namespace App\Services;

use App\Models\DeviceToken;
use Google\Auth\Credentials\ServiceAccountCredentials;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Illuminate\Support\Facades\Log;
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

    /**
     * Sends to exactly ONE token.
     * Returns decoded JSON on success.
     * Throws RequestException on HTTP errors (401/403/404/400 etc).
     */
    public function sendToToken(string $token, array $payload): array
    {
        $projectId = config('services.fcm.project_id');
        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";

        $notificationId = $payload['notification_id'] ?? (string) Str::uuid();

        $body = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => (string)($payload['title'] ?? ''),
                    'body'  => (string)($payload['body'] ?? ''),
                ],
                'data' => array_map('strval', array_merge([
                    'notification_id' => $notificationId,
                    'ts' => (string) time(),
                ], $payload['data'] ?? [])),
            ],
        ];

        $res = $this->http->post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->accessToken(),
                'Content-Type'  => 'application/json',
            ],
            'json' => $body,
        ]);

        return json_decode((string)$res->getBody(), true) ?? [];
    }

    /**
     * Send to MANY tokens (given list).
     * - Logs real FCM error body
     * - Marks token invalid ONLY for token-dead errors
     */
    public function sendToTokens(array $tokens, array $payload): array
    {
        $ok = 0;
        $fail = 0;
        $errors = [];

        foreach ($tokens as $t) {
            try {
                $this->sendToToken($t, $payload);
                $ok++;
            } catch (RequestException $e) {
                $fail++;

                $status = $e->getResponse()?->getStatusCode();
                $rawBody = $e->getResponse() ? (string) $e->getResponse()->getBody() : null;
                $decoded = $rawBody ? json_decode($rawBody, true) : null;

                // Log the REAL reason from FCM
                Log::warning('FCM_SEND_FAILED', [
                    'token_prefix' => substr((string)$t, 0, 25),
                    'http_status'  => $status,
                    'body'         => $decoded ?? $rawBody,
                ]);

                $fcmStatus = $decoded['error']['status'] ?? null;
                $fcmMessage = $decoded['error']['message'] ?? null;

                $errors[] = [
                    'http_status' => $status,
                    'fcm_status'  => $fcmStatus,
                    'message'     => $fcmMessage,
                ];

                // ✅ Mark invalid ONLY for token-dead cases
                // v1 commonly returns 404 NOT_FOUND for unregistered/invalid token
                $tokenDead =
                    ($status === 404) ||
                    ($fcmStatus === 'NOT_FOUND') ||
                    ($fcmStatus === 'UNREGISTERED') ||
                    // sometimes invalid token format hits INVALID_ARGUMENT
                    (($status === 400 || $fcmStatus === 'INVALID_ARGUMENT') && is_string($fcmMessage) && str_contains(strtolower($fcmMessage), 'registration token'));

                if ($tokenDead) {
                    DeviceToken::where('token', $t)->update(['is_valid' => false]);
                }
            } catch (\Throwable $e) {
                $fail++;
                Log::error('FCM_SEND_EXCEPTION', [
                    'token_prefix' => substr((string)$t, 0, 25),
                    'msg' => $e->getMessage(),
                ]);

                // ❌ DO NOT mark invalid here (could be timeout/etc)
            }
        }

        return [
            'ok' => $ok,
            'fail' => $fail,
            'total' => count($tokens),
            'errors' => $errors,
        ];
    }

    public function sendToUser(int $userId, array $payload): array
    {
        $tokens = DeviceToken::query()
            ->where('user_id', $userId)
            ->where('is_valid', 1)
            ->orderByDesc('id')
            ->pluck('token')
            ->all();

        return $this->sendToTokens($tokens, $payload);
    }
}
