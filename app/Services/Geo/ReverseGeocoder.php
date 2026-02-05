<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ReverseGeocoder
{
    public function pincodeFromLatLon(float $lat, float $lon): ?string
    {
        $cacheKey = "revgeo:pincode:" . round($lat, 5) . ":" . round($lon, 5);
        return Cache::remember($cacheKey, Config::get('geocoding.cache_ttl'), function () use ($lat, $lon) {
            $pin = $this->viaNominatim($lat, $lon);
            if (!$pin) {
                $pin = $this->viaGoogle($lat, $lon); // fallback if key configured
            }
            return $pin ?: null;
        });
    }

    private function viaNominatim(float $lat, float $lon): ?string
    {
        if (Config::get('geocoding.provider') !== 'nominatim' && !Config::get('geocoding.nominatim.endpoint')) {
            return null;
        }

        $endpoint = Config::get('geocoding.nominatim.endpoint');
        $ua       = Config::get('geocoding.nominatim.user_agent');
        $email    = Config::get('geocoding.nominatim.email');

        $resp = Http::withHeaders(['User-Agent' => $ua])
            ->timeout(Config::get('geocoding.timeout'))
            ->retry(2, 200)
            ->get($endpoint, [
                'format' => 'jsonv2',
                'lat'    => $lat,
                'lon'    => $lon,
                'addressdetails' => 1,
                'zoom'   => 18,
                'email'  => $email,
            ]);

        if (!$resp->ok()) return null;

        $addr = $resp->json('address', []);
        $pin  = $addr['postcode'] ?? null;

        // Some Indian results include 6-digit within strings; normalize.
        if (!$pin && is_array($addr)) {
            $flat = implode(' ', array_values($addr));
            $pin = $this->extractSixDigit($flat);
        }
        return $this->normalizePin($pin);
    }

    private function viaGoogle(float $lat, float $lon): ?string
    {
        $key = Config::get('geocoding.google.key');
        $endpoint = Config::get('geocoding.google.endpoint');
        if (!$key || !$endpoint) return null;

        $resp = Http::timeout(Config::get('geocoding.timeout'))
            ->retry(2, 200)
            ->get($endpoint, [
                'latlng' => "{$lat},{$lon}",
                'key'    => $key,
                'result_type' => 'postal_code|street_address|sublocality|neighborhood|administrative_area_level_3',
            ]);

        if (!$resp->ok()) return null;

        $results = $resp->json('results', []);
        foreach ($results as $r) {
            foreach ($r['address_components'] ?? [] as $c) {
                if (in_array('postal_code', $c['types'] ?? [], true)) {
                    return $this->normalizePin($c['long_name'] ?? null);
                }
            }
            // Fallback scan
            $pin = $this->extractSixDigit($r['formatted_address'] ?? '');
            if ($pin) return $this->normalizePin($pin);
        }
        return null;
    }

    private function extractSixDigit(?string $text): ?string
    {
        if (!$text) return null;
        if (preg_match('/\b(\d{6})\b/u', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    private function normalizePin(?string $pin): ?string
    {
        $pin = $pin ? preg_replace('/\D/', '', (string) $pin) : null;
        if ($pin && Str::length($pin) === 6) return $pin;
        return null;
    }
}
