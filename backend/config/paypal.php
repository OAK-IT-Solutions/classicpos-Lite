<?php

return [
    'client_id' => env('PAYPAL_CLIENT_ID', ''),
    'client_secret' => env('PAYPAL_CLIENT_SECRET', ''),

    'mode' => env('PAYPAL_MODE', 'sandbox'),
    'webhook_id' => env('PAYPAL_WEBHOOK_ID', ''),

    'currency' => env('PAYPAL_CURRENCY', 'USD'),

    'api_url' => env('PAYPAL_MODE', 'sandbox') === 'live'
        ? 'https://api-m.paypal.com'
        : 'https://api-m.sandbox.paypal.com',

    'webhook_url' => env('PAYPAL_WEBHOOK_URL', env('APP_URL') . '/api/v1/billing/paypal/webhook'),

    'status_map' => [
        'COMPLETED' => 'success',
        'APPROVED' => 'pending',
        'CREATED' => 'pending',
        'VOIDED' => 'failed',
        'DECLINED' => 'failed',
        'PARTIALLY_REFUNDED' => 'refunded',
        'REFUNDED' => 'refunded',
        'FAILED' => 'failed',
        'CANCELLED' => 'failed',
    ],
];
