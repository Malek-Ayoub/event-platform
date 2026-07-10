<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbox Worker (Phase 8.1)
    |--------------------------------------------------------------------------
    */

    'batch_size' => (int) env('OUTBOX_BATCH_SIZE', 50),

    'max_attempts' => (int) env('OUTBOX_MAX_ATTEMPTS', 5),

    /*
    | Seconds to wait before retrying after each failed attempt (indexed by attempt number - 1).
    */
    'retry_backoff_seconds' => [
        30,
        60,
        120,
        300,
        600,
    ],

    /*
    | Processing rows older than this are considered stale (worker crash) and re-queued.
    */
    'stale_processing_minutes' => (int) env('OUTBOX_STALE_PROCESSING_MINUTES', 15),

];
