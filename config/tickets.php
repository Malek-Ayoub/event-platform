<?php

return [
    'number' => [
        'event_prefix' => 'EV',
        'event_id_pad' => 6,
        'sequence_pad' => 6,
    ],

    'qr' => [
        'disk' => env('TICKET_QR_DISK', 'local'),
        'size' => 300,
        'margin' => 10,
    ],

    'pdf' => [
        'disk' => env('TICKET_PDF_DISK', 'local'),
    ],

    'artifact' => [
        'default_version' => 1,
    ],

    'snapshot' => [
        'default_currency' => env('TICKET_SNAPSHOT_CURRENCY', 'USD'),
    ],
];
