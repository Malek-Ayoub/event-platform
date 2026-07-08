<?php

namespace App\Services\Webhooks\Data;

use App\Enums\Webhooks\WebhookEventType;

/** Provider-agnostic domain command mapped from a verified webhook payload. */
readonly class WebhookDomainCommand
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public WebhookEventType $eventType,
        public string $provider,
        public string $correlationId,
        public array $payload,
    ) {}
}
