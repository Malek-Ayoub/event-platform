<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;

readonly class WebhookPayload extends BaseDTO
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $parsedPayload
     */
    public function __construct(
        public string $provider,
        public string $providerEventId,
        public string $rawBody,
        public array $headers,
        public array $parsedPayload = [],
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
            parsedPayload: isset($data['parsed_payload']) && is_array($data['parsed_payload'])
                ? $data['parsed_payload']
                : [],
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
        ];
    }
}
