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
    public function sendNoDeliveryAlert(string $phoneNumber, array $payloadData): array
    {
        $templateKey = $payloadData['template_key'] ?? 'no_milk_sudden';
        $template = $this->getTemplateConfig($templateKey);

        $bodyValues = [];

        foreach (($template['body_values'] ?? []) as $field) {
            $bodyValues[] = (string) ($payloadData[$field] ?? '');
        }

        $payload = [
            "countryCode"  => "+91",
            "phoneNumber"  => $phoneNumber,
            "callbackData" => "no_order_delivery",
            "type"         => "Template",
            "template"     => [
                "name"         => $template['template_name'],
                "languageCode" => $template['language_code'] ?? 'en',
                "bodyValues"   => $bodyValues,
            ],
        ];


        if (!config('services.interakt_dayli.enabled')) {

            $testNumber = env('INTERAKT_TEST_NUMBER');

            if ($testNumber) {
                $payload['phoneNumber'] = $testNumber;
            }

            Log::info('INTERAKT TEST MODE - real customer replaced with test number', [
                'original_phone' => $phoneNumber,
                'test_phone' => $payload['phoneNumber'],
                'payload' => $payload,
            ]);
        }
        $response = Http::withHeaders([
            "Content-Type"  => "application/json",
            "Authorization" => "Basic " . $this->apiKey,
        ])->post($this->baseUrl, $payload);

        Log::info('Interakt No Delivery response', [
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

    private function getTemplateConfig(string $key): array
    {
        $path = storage_path('app/interakt_templates.json');

        if (!file_exists($path)) {
            throw new \Exception('Interakt templates JSON not found: ' . $path);
        }

        $json = json_decode(file_get_contents($path), true);

        if (!isset($json[$key])) {
            throw new \Exception("Interakt template key not found: {$key}");
        }

        return $json[$key];
    }
}
