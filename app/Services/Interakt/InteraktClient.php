<?php

namespace App\Services\Interakt;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\RequestException;

class InteraktClient
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        // ✅ Base should be root
        $this->baseUrl = 'https://api.interakt.ai';
        $this->apiKey  = (string) config('services.interakt_dayli.api_key');
    }

    protected function client()
    {
        return Http::withHeaders([
            'Authorization' => 'Basic ' . $this->apiKey,
            'Content-Type'  => 'application/json',
        ])->baseUrl($this->baseUrl);
    }

    protected function post(string $uri, array $payload = []): array
    {
        $res = $this->client()->post($uri, $payload);

        if ($res->failed()) {
            throw new RequestException($res);
        }

        return $res->json() ?? [];
    }

    /**
     * ✅ Get Users / Contacts Retrieval API
     * POST https://api.interakt.ai/v1/public/apis/users/?offset=0&limit=100
     */
    public function listUsers(int $offset, int $limit, array $filters = []): array
    {
        $uri = "/v1/public/apis/users/?offset={$offset}&limit={$limit}";
        return $this->post($uri, $filters);
    }

    /**
     * ✅ Send Template Message API
     * POST https://api.interakt.ai/v1/public/message/
     */
    public function sendTemplateMessage(array $payload): array
    {
        return $this->post('/v1/public/message/', $payload);
    }
}
