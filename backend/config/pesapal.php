<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pesapal Configuration
    |--------------------------------------------------------------------------
    |
    | Pesapal v3 API credentials and settings.
    | Get keys from https://pesapal.com/developer/dashboard
    |
    */

    'consumer_key' => env('PESAPAL_CONSUMER_KEY', ''),
    'consumer_secret' => env('PESAPAL_CONSUMER_SECRET', ''),

    'iframe_url' => env('PESAPAL_IFRAME_URL', 'https://pesapal.com/api/_pesapalv3/api/Email/Initiate'),
    'api_url' => env('PESAPAL_API_URL', 'https://pesapal.com/api/_pesapalv3'),

    'callback_url' => env('PESAPAL_CALLBACK_URL', env('APP_URL') . '/api/v1/billing/callback'),
    'ipn_url' => env('PESAPAL_IPN_URL', env('APP_URL') . '/api/v1/billing/ipn'),

    'currency' => env('PESAPAL_CURRENCY', 'USD'),

    'status_map' => [
        '0' => 'pending',     // Not completed
        '1' => 'completed',   // Completed
        '2' => 'failed',      // Failed
        '3' => 'cancelled',   // Cancelled
    ],
];
