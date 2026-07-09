<?php

namespace App\DTOs\Payments;

use App\DTOs\BaseDTO;

readonly class VerifyPaymentDTO extends BaseDTO
{
    public function __construct(
        public string $transactionNumber,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            transactionNumber: (string) $data['transaction_number'],
        );
    }

    public function toArray(): array
    {
        return [
            'transaction_number' => $this->transactionNumber,
        ];
    }
}
