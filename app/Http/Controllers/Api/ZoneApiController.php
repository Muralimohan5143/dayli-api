<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ZoneApiController extends Controller
{
    public function resolveFromLatLng()
    {


        // Read & validate
        $lat = request('lat');
        $lng = request('lng');

        if ($lat === null || $lng === null || $lat === '' || $lng === '') {
            return response()->json(['message' => 'lat and lng are required'], 422);
        }

        $lat = (float) $lat;
        $lng = (float) $lng;
        $city = $this->reverseGeocodeToCity($lat, $lng);

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return response()->json(['message' => 'Invalid lat/lng values'], 422);
        }

        // STEP 1: Reverse geocode -> pincode (may be null, that's OK)
        $pincode = $this->reverseGeocodeToPincode($lat, $lng);

        // STEP 2: Primary -> pincode mapping
        if ($pincode) {
            $zone = Zone::findByPinCode($pincode);

            if ($zone && $zone->status === 'active') {
                return response()->json([
                    'zone_id'   => $zone->id,
                    'zone_code' => $zone->code,
                    'zone_name' => $zone->name,
                    'city' => $city,
                    'pincode'   => $pincode,
                    'source'    => 'pincode',
                ]);
            }
        }

        // STEP 3: Fallback -> nearest focal point (only active zones with focal coords)
        $zone = Zone::where('status', 'active')
            ->whereNotNull('focal_lat')
            ->whereNotNull('focal_lon')
            ->selectRaw("
                id, code, name, focal_lat, focal_lon,
                (
                  6371 * acos(
                    cos(radians(?)) *
                    cos(radians(focal_lat)) *
                    cos(radians(focal_lon) - radians(?)) +
                    sin(radians(?)) *
                    sin(radians(focal_lat))
                  )
                ) AS distance_km
            ", [$lat, $lng, $lat])
            ->orderBy('distance_km')
            ->first();

        if (!$zone) {
            return response()->json([
                'message' => 'Service not available in this area',
                'pincode' => $pincode,
            ], 404);
        }

        return response()->json([
            'zone_id' => $zone->id,
            'zone_code' => $zone->code,
            'zone_name' => $zone->name,
            'city' => $city,
            'pincode' => $pincode,
            'source' => 'focal_fallback',
            'note' => $pincode ? null : 'Google did not return postal_code for this point',
        ]);
    }

    protected function reverseGeocodeToPincode(float $lat, float $lng): ?string
    {
        $key = 'AIzaSyBOsJKWtEmsaT1-_41Qr13alOGgEKeQfQ0'; //config('services.google.maps_api_key');
        if (!$key) {
            Log::warning('GOOGLE_MAPS_API_KEY missing');
            return null;
        }

        // 1) Try: result_type=postal_code (best when it works)
        $pin = $this->googleGeocodeGetPincode($lat, $lng, $key, true);
        if ($pin) return $pin;

        // 2) Fallback: normal reverse geocode
        return $this->googleGeocodeGetPincode($lat, $lng, $key, false);
    }

    private function googleGeocodeGetPincode(float $lat, float $lng, string $key, bool $postalOnly): ?string
    {
        return '518002';
        //todo:remove hardcode value above line
        $params = [
            'latlng' => "{$lat},{$lng}",
            'key'    => $key,
            'region' => 'IN',
        ];

        if ($postalOnly) {
            $params['result_type'] = 'postal_code';
        }

        $res = Http::timeout(8)->get('https://maps.googleapis.com/maps/api/geocode/json', $params);

        if (!$res->successful()) {
            Log::warning('GEOCODE_HTTP_FAIL', [
                'postalOnly' => $postalOnly,
                'http' => $res->status(),
                'body' => $res->body(),
            ]);
            return null;
        }

        $status = $res->json('status');
        if ($status !== 'OK') {
            Log::warning('GEOCODE_NOT_OK', [
                'postalOnly' => $postalOnly,
                'status' => $status,
                'error_message' => $res->json('error_message'),
            ]);
            return null;
        }

        foreach ($res->json('results', []) as $result) {
            foreach (($result['address_components'] ?? []) as $component) {
                $types = $component['types'] ?? [];
                if (in_array('postal_code', $types, true)) {
                    return $component['long_name'] ?? null;
                }
            }
        }

        // No postal code found in results
        return null;
    }

    protected function reverseGeocodeToCity(float $lat, float $lng): ?string
    {
        $res = Http::withHeaders([
            'User-Agent' => 'Dayli/1.0'
        ])->get(
            'https://nominatim.openstreetmap.org/reverse',
            [
                'lat' => $lat,
                'lon' => $lng,
                'format' => 'json',
            ]
        );

        if (!$res->successful()) {
            return null;
        }

        $address = $res->json('address');

        return $address['city']
            ?? $address['town']
            ?? $address['county']
            ?? $address['state_district']
            ?? null;
    }
}
