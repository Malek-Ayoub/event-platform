<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;

readonly class WebhookVerificationResult extends BaseDTO
{
    public function __construct(
        public bool $verified,
        public ?string $providerEventId = null,
        public ?string $failureReason = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            verified: (bool) $data['verified'],
            providerEventId: isset($data['provider_event_id']) ? (string) $data['provider_event_id'] : null,
            failureReason: isset($data['failure_reason']) ? (string) $data['failure_reason'] : null,
        );
    }

    public static function success(?string $providerEventId = null): self
    {
        return new self(verified: true, providerEventId: $providerEventId);
    }

    public static function failure(string $reason): self
    {
        return new self(verified: false, failureReason: $reason);
    }

    public function toArray(): array
    {
        return [
            'verified' => $this->verified,
            'provider_event_id' => $this->providerEventId,
            'failure_reason' => $this->failureReason,
        ];
    }
}
