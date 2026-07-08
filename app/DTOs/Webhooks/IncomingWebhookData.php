<?php

namespace App\DTOs\Webhooks;

use App\DTOs\BaseDTO;

/** Normalized inbound webhook request (HTTP layer → WebhookService). */
readonly class IncomingWebhookData extends BaseDTO
{
    /**
     * @param  array<string, mixed>  $headers
     * @param  array<string, mixed>  $parsedPayload
     */
    public function __construct(
        public string $provider,
        public string $providerEventId,
        public string $rawBody,
        public array $headers,
        public array $parsedPayload,
        public ?string $signature = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            provider: (string) $data['provider'],
            providerEventId: (string) $data['provider_event_id'],
            rawBody: (string) $data['raw_body'],
            headers: isset($data['headers']) && is_array($data['headers']) ? $data['headers'] : [],
            parsedPayload: isset($data['parsed_payload']) && is_array($data['parsed_payload']) ? $data['parsed_payload'] : [],
            signature: isset($data['signature']) ? (string) $data['signature'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'provider' => $this->provider,
            'provider_event_id' => $this->providerEventId,
            'raw_body' => $this->rawBody,
            'headers' => $this->headers,
            'parsed_payload' => $this->parsedPayload,
            'signature' => $this->signature,
        ];
    }
}
