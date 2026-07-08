<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;
use App\Enums\Payments\GatewayOutcome;

readonly class WebhookVerificationResult extends BaseDTO
{
    public function __construct(
        public bool $verified,
        public GatewayOutcome $outcome,
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
            outcome: isset($data['outcome'])
                ? GatewayOutcome::from((string) $data['outcome'])
                : GatewayOutcome::Unknown,
            providerEventId: isset($data['provider_event_id']) ? (string) $data['provider_event_id'] : null,
            failureReason: isset($data['failure_reason']) ? (string) $data['failure_reason'] : null,
        );
    }

    public static function success(?string $providerEventId = null): self
    {
        return new self(
            verified: true,
            outcome: GatewayOutcome::Success,
            providerEventId: $providerEventId,
        );
    }

    public static function failure(string $reason, GatewayOutcome $outcome = GatewayOutcome::InvalidSignature): self
    {
        return new self(
            verified: false,
            outcome: $outcome,
            failureReason: $reason,
        );
    }

    public function toArray(): array
    {
        return [
            'verified' => $this->verified,
            'outcome' => $this->outcome->value,
            'provider_event_id' => $this->providerEventId,
            'failure_reason' => $this->failureReason,
        ];
    }
}
