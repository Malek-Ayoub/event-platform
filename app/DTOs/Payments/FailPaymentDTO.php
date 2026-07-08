<?php

namespace App\DTOs\Payments;

use App\DTOs\BaseDTO;

readonly class FailPaymentDTO extends BaseDTO
{
    public function __construct(
        public ?string $reason,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reason: isset($data['reason']) ? (string) $data['reason'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'reason' => $this->reason,
        ], fn ($value) => $value !== null);
    }
}
