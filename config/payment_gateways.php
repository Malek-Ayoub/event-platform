<?php

return [

    'http' => [
        'connect_timeout' => (int) env('PAYMENT_GATEWAY_CONNECT_TIMEOUT', 5),
        'request_timeout' => (int) env('PAYMENT_GATEWAY_REQUEST_TIMEOUT', 30),
        'retry_attempts' => (int) env('PAYMENT_GATEWAY_RETRY_ATTEMPTS', 2),
        'retry_delay_ms' => (int) env('PAYMENT_GATEWAY_RETRY_DELAY_MS', 250),
    ],

    'instruction_ttl_hours' => (int) env('PAYMENT_INSTRUCTION_TTL_HOURS', 24),

    'providers' => [

        'shamcash' => [
            'base_url' => env('SHAMCASH_BASE_URL', 'https://api.shamcash.example'),
            'api_key' => env('SHAMCASH_API_KEY', ''),
            'refund_path' => env('SHAMCASH_REFUND_PATH', '/v1/refunds'),
        ],

        'syriatel_cash' => [
            'base_url' => env('SYRIATEL_CASH_BASE_URL', 'https://api.syriatelcash.example'),
            'api_key' => env('SYRIATEL_CASH_API_KEY', ''),
            'refund_path' => env('SYRIATEL_CASH_REFUND_PATH', '/api/payment/refund'),
        ],

        'apisyria' => [
            'base_url' => env('APISYRIA_BASE_URL', 'https://apisyria.com/api/v1'),
            'api_key' => env('APISYRIA_API_KEY', ''),
            'timeouts' => [
                'connect' => (int) env('APISYRIA_CONNECT_TIMEOUT', 10),
                'request' => (int) env('APISYRIA_TIMEOUT', 20),
            ],
            'retry' => [
                'attempts' => (int) env('APISYRIA_RETRY_ATTEMPTS', 2),
                'delay_ms' => (int) env('APISYRIA_RETRY_DELAY_MS', 500),
            ],
        ],

    ],

];
