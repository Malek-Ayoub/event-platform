<?php

namespace App\DTOs\Payments;

use App\DTOs\BaseDTO;

readonly class CompletePaymentDTO extends BaseDTO
{
    public function __construct(
        public ?string $paymentMethod,
        public ?string $paymentReference,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            paymentMethod: isset($data['payment_method']) ? (string) $data['payment_method'] : null,
            paymentReference: isset($data['payment_reference']) ? (string) $data['payment_reference'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'payment_method' => $this->paymentMethod,
            'payment_reference' => $this->paymentReference,
        ], fn ($value) => $value !== null);
    }
}
