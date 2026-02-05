<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class InteraktService
{
    protected string $baseUrl;
    protected string $apiKey;

    public function __construct()
    {
        $this->apiKey  = (string) config('services.interakt_dayli.api_key', '');

        // 🔥 Hardcode correct endpoint for now
        $this->baseUrl = 'https://api.interakt.ai/v1/public/message/';
    }
    /**
     * $phoneNumber = last 10 digits (e.g. 6364168111)
     */
    public function sendOtp(string $phoneNumber, string $otp): array
    {
        // baseUrl already points to /v1/public/message
        $url = $this->baseUrl;

        $payload = [
            "countryCode"  => "+91",
            "phoneNumber"  => $phoneNumber,
            "callbackData" => "otp_login",
            "type"         => "Template",
            "template"     => [
                "name"         => "otp_login2",
                "languageCode" => "en",
                "bodyValues"   => [$otp],
                "buttonValues" => (object) [
                    "0" => [$otp],
                ],
            ],
        ];

        $response = Http::withHeaders([
            "Content-Type"  => "application/json",
            "Authorization" => "Basic " . $this->apiKey,
        ])->post($url, $payload);

        Log::info('Interakt OTP response', [
            'status'  => $response->status(),
            'body'    => $response->json(),
            'payload' => $payload,
        ]);

        return [
            'ok'      => $response->successful(),
            'status'  => $response->status(),
            'body'    => $response->json(),
            'payload' => $payload,
        ];
    }
}
