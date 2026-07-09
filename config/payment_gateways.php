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

        // Batch 7.6 — Manual Wallet Transfer (IMPLEMENTATION_ROADMAP.md §7.9).
        // API Syria exposes only a transaction-lookup endpoint (`find_tx`) —
        // no hosted checkout, no webhooks. `merchant_account` is the wallet
        // customers transfer to; verification rejects any lookup whose
        // `receiver_account` does not match it (§7.9.6 rule #4).
        'apisyria' => [
            'base_url' => env('APISYRIA_BASE_URL', 'https://api.syria.example'),
            'api_key' => env('APISYRIA_API_KEY', ''),
            'merchant_account' => env('APISYRIA_MERCHANT_ACCOUNT', ''),
            'verify_transaction_path' => env('APISYRIA_VERIFY_TRANSACTION_PATH', '/find_tx'),
        ],

    ],

];
