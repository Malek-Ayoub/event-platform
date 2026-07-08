<?php

return [

    'http' => [
        'connect_timeout' => (int) env('PAYMENT_GATEWAY_CONNECT_TIMEOUT', 5),
        'request_timeout' => (int) env('PAYMENT_GATEWAY_REQUEST_TIMEOUT', 30),
        'retry_attempts' => (int) env('PAYMENT_GATEWAY_RETRY_ATTEMPTS', 2),
        'retry_delay_ms' => (int) env('PAYMENT_GATEWAY_RETRY_DELAY_MS', 250),
    ],

    'providers' => [

        'shamcash' => [
            'base_url' => env('SHAMCASH_BASE_URL', 'https://api.shamcash.example'),
            'api_key' => env('SHAMCASH_API_KEY', ''),
            'webhook_secret' => env('SHAMCASH_WEBHOOK_SECRET', ''),
            'signature_header' => env('SHAMCASH_SIGNATURE_HEADER', 'X-ShamCash-Signature'),
            'initiate_path' => env('SHAMCASH_INITIATE_PATH', '/v1/payments'),
            'refund_path' => env('SHAMCASH_REFUND_PATH', '/v1/refunds'),
        ],

        'syriatel_cash' => [
            'base_url' => env('SYRIATEL_CASH_BASE_URL', 'https://api.syriatelcash.example'),
            'api_key' => env('SYRIATEL_CASH_API_KEY', ''),
            'webhook_secret' => env('SYRIATEL_CASH_WEBHOOK_SECRET', ''),
            'signature_header' => env('SYRIATEL_CASH_SIGNATURE_HEADER', 'X-Syriatel-Signature'),
            'initiate_path' => env('SYRIATEL_CASH_INITIATE_PATH', '/api/payment/create'),
            'refund_path' => env('SYRIATEL_CASH_REFUND_PATH', '/api/payment/refund'),
        ],

    ],

];
