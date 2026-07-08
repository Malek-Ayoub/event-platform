<?php

namespace App\Services\Webhooks\Mappers;

use App\DTOs\Payments\Gateway\WebhookPayload;
use App\Enums\Webhooks\WebhookEventType;
use App\Services\Webhooks\Data\WebhookDomainCommand;

final class RefundProcessedMapper extends AbstractWebhookEventMapper
{
    public function eventType(): WebhookEventType
    {
        return WebhookEventType::RefundProcessed;
    }

    public function map(WebhookPayload $payload): WebhookDomainCommand
    {
        $parsed = $payload->parsedPayload;

        $this->requireField($parsed, 'refund_id', $this->eventType());

        return $this->command($payload, $parsed);
    }
}
