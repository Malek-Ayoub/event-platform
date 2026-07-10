<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Notification Channels (Phase 8.2)
    |--------------------------------------------------------------------------
    */

    'channels' => [
        'email',
    ],

    'placeholders' => [
        'ticket_download_url' => '#pending-tickets/{{order_number}}',
    ],

    /*
    | Platform-default template fallbacks when no DB template exists.
    | Venue-specific rows in email_templates override these slugs.
    |
    | @var array<string, array{subject: string, body: string, variables?: list<string>}>
    */
    'templates' => [
        'order.paid' => [
            'subject' => 'Your tickets for {{event_name}}',
            'body' => <<<'TEXT'
Hello {{customer_name}},

Thank you for your purchase.

Order: {{order_number}}
Tickets: {{ticket_count}}
Total: {{total}}

{{ticket_download_url}}

TEXT,
            'variables' => [
                'customer_name',
                'event_name',
                'order_number',
                'ticket_count',
                'total',
                'amount',
                'ticket_download_url',
            ],
        ],
        'refund.processed' => [
            'subject' => 'Refund processed for order {{order_number}}',
            'body' => <<<'TEXT'
Hello {{customer_name}},

Your refund for order {{order_number}} has been processed.

Amount: {{refund_amount}}

TEXT,
            'variables' => [
                'customer_name',
                'order_number',
                'refund_amount',
            ],
        ],
        'event.cancelled' => [
            'subject' => '{{event_name}} has been cancelled',
            'body' => <<<'TEXT'
Hello {{customer_name}},

We regret to inform you that {{event_name}} has been cancelled.

If you purchased tickets, refund details will follow shortly.

TEXT,
            'variables' => [
                'customer_name',
                'event_name',
            ],
        ],
    ],

];
