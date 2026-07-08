<?php

namespace App\Enums\Webhooks;

enum WebhookEventType: string
{
    case PaymentCompleted = 'payment.completed';
    case PaymentFailed = 'payment.failed';
    case RefundProcessed = 'refund.processed';
}
