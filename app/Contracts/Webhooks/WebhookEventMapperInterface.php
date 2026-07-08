<?php

namespace App\Contracts\Webhooks;

use App\DTOs\Payments\Gateway\WebhookPayload;
use App\Enums\Webhooks\WebhookEventType;
use App\Services\Webhooks\Data\WebhookDomainCommand;

interface WebhookEventMapperInterface
{
    public function eventType(): WebhookEventType;

    public function map(WebhookPayload $payload): WebhookDomainCommand;
}
