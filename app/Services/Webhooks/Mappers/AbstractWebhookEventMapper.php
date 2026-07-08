<?php

namespace App\Services\Webhooks\Mappers;

use App\Contracts\Webhooks\WebhookEventMapperInterface;
use App\DTOs\Payments\Gateway\WebhookPayload;
use App\Enums\Webhooks\WebhookEventType;
use App\Exceptions\Webhooks\UnsupportedWebhookEventException;
use App\Services\Webhooks\Data\WebhookDomainCommand;
use App\Support\Webhooks\WebhookCorrelation;

abstract class AbstractWebhookEventMapper implements WebhookEventMapperInterface
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function command(WebhookPayload $webhookPayload, array $payload): WebhookDomainCommand
    {
        return new WebhookDomainCommand(
            eventType: $this->eventType(),
            provider: $webhookPayload->provider,
            correlationId: WebhookCorrelation::id($webhookPayload->provider, $webhookPayload->providerEventId),
            payload: $payload,
        );
    }

    protected function requireField(array $payload, string $field, WebhookEventType $eventType): string
    {
        if (! isset($payload[$field]) || (string) $payload[$field] === '') {
            throw UnsupportedWebhookEventException::forEventType($eventType->value." missing {$field}");
        }

        return (string) $payload[$field];
    }
}
