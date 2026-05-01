<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],
    // 'interakt_dayli' => [
    //     'api_key'   => env('INTERAKT_DAYLI_API_KEY'),
    //     'base_url'  => env('INTERAKT_DAYLI_BASE_URL', 'https://api.interakt.ai/v1/public/apis'),
    // ],


    'interakt_dayli' => [
        'api_key'  => env('INTERAKT_DAYLI_API_KEY'),
        // 👇 use MESSAGE endpoint here, not /apis
        'base_url' => env('INTERAKT_DAYLI_BASE_URL', 'https://api.interakt.ai/v1/public/message/'),
    ],
    'interakt' => [
        'key' => env('INTERAKT_API_KEY'),
        'url' => env('INTERAKT_API_URL', 'https://api.interakt.ai/v1/public/message/'),
        'enabled'  => env('INTERAKT_ENABLED', true),
    ],


    // ✅ Add this for Google Maps / Geocoding
    'google' => [
        'maps_api_key' => env('GOOGLE_MAPS_API_KEY'),
    ],
    'shopify_dayli' => [
        'store_domain' => env('SHOPIFY_DAYLI_STORE_DOMAIN'),
        'access_token' => env('SHOPIFY_DAYLI_ACCESS_TOKEN'),
        'api_version'  => env('SHOPIFY_DAYLI_API_VERSION', '2025-07'),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'service_account_json' => env('FCM_SERVICE_ACCOUNT_JSON'),
    ],


];
