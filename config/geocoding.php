<?php

return [
    'provider' => env('GEOCODING_PROVIDER', 'nominatim'), // nominatim|google
    'timeout'  => (float) env('GEOCODING_TIMEOUT', 5.0),

    // Nominatim (OpenStreetMap) – FREE, but be polite with a UA & email.
    'nominatim' => [
        'endpoint' => env('GEOCODING_NOMINATIM_URL', 'https://nominatim.openstreetmap.org/reverse'),
        'email'    => env('GEOCODING_NOMINATIM_EMAIL', 'admin@dayli.in'),
        'user_agent' => env('GEOCODING_NOMINATIM_UA', 'DayliApp/1.0 (+https://dayli.in)'),
    ],

    // Google Maps Geocoding API (optional)
    'google' => [
        'endpoint' => env('GEOCODING_GOOGLE_URL', 'https://maps.googleapis.com/maps/api/geocode/json'),
        'key'      => env('GOOGLE_MAPS_API_KEY'),
    ],

    // Cache TTL (seconds)
    'cache_ttl' => (int) env('GEOCODING_CACHE_TTL', 86400), // 24h
];
