<?php

namespace App\Services\Webhooks;

use App\Contracts\Webhooks\WebhookEventMapperInterface;
use App\DTOs\Payments\Gateway\WebhookPayload;
use App\Enums\Webhooks\WebhookEventType;
use App\Exceptions\Webhooks\UnsupportedWebhookEventException;
use App\Services\Webhooks\Data\WebhookDomainCommand;

final class WebhookDomainCommandMapperRegistry
{
    /**
     * @param  array<string, WebhookEventMapperInterface>  $mappers
     */
    public function __construct(
        private array $mappers,
    ) {}

    public function map(WebhookPayload $payload): WebhookDomainCommand
    {
        $parsed = $payload->parsedPayload;
        $eventTypeValue = (string) ($parsed['event_type'] ?? $parsed['type'] ?? '');

        if ($eventTypeValue === '' || ! isset($this->mappers[$eventTypeValue])) {
            throw UnsupportedWebhookEventException::forEventType($eventTypeValue !== '' ? $eventTypeValue : 'unknown');
        }

        return $this->mappers[$eventTypeValue]->map($payload);
    }

    /**
     * @return list<WebhookEventType>
     */
    public function registeredEventTypes(): array
    {
        return array_map(
            static fn (WebhookEventMapperInterface $mapper): WebhookEventType => $mapper->eventType(),
            array_values($this->mappers),
        );
    }
}
