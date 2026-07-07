<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform Queue Names
    |--------------------------------------------------------------------------
    |
    | Locked architecture queue names used across checkout, outbox, and
    | notification workers (IMPLEMENTATION_ROADMAP Phase 1).
    |
    */

    'queues' => [
        'default' => env('QUEUE_NAME_DEFAULT', 'default'),
        'outbox' => env('QUEUE_NAME_OUTBOX', 'outbox'),
        'notifications' => env('QUEUE_NAME_NOTIFICATIONS', 'notifications'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database Engine Notes (§50)
    |--------------------------------------------------------------------------
    |
    | PostgreSQL: partial unique indexes for soft-delete-safe uniqueness.
    | MySQL: generated *_active columns + unique indexes.
    |
    */

    'database_engine' => env('DB_ENGINE', env('DB_CONNECTION', 'sqlite')),

    'seed' => [
        'super_admin_email' => env('SUPER_ADMIN_EMAIL', 'superadmin@event-platform.test'),
        'super_admin_password' => env('SUPER_ADMIN_PASSWORD', 'ChangeMeNow!123'),
    ],

    'auth' => [
        'token_name' => env('AUTH_TOKEN_NAME', 'api'),
        'frontend_reset_password_url' => env('FRONTEND_RESET_PASSWORD_URL', env('APP_URL').'/reset-password'),
    ],

];
