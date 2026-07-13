<?php

namespace App\DTOs\Commissions;

use App\DTOs\BaseDTO;
use App\Enums\FinancialDomain\CommissionPaymentMethod;

readonly class RecordCommissionPaymentDTO extends BaseDTO
{
    public function __construct(
        public int $venueId,
        public string $amount,
        public string $currency,
        public CommissionPaymentMethod $paymentMethod,
        public string $receivedAt,
        public ?int $paymentAccountId,
        public ?string $referenceNumber,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            venueId: (int) $data['venue_id'],
            amount: (string) $data['amount'],
            currency: strtoupper((string) ($data['currency'] ?? 'USD')),
            paymentMethod: $data['payment_method'] instanceof CommissionPaymentMethod
                ? $data['payment_method']
                : CommissionPaymentMethod::from((string) $data['payment_method']),
            receivedAt: (string) $data['received_at'],
            paymentAccountId: isset($data['payment_account_id']) ? (int) $data['payment_account_id'] : null,
            referenceNumber: isset($data['reference_number']) ? (string) $data['reference_number'] : null,
            notes: isset($data['notes']) ? (string) $data['notes'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'venue_id' => $this->venueId,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'payment_method' => $this->paymentMethod->value,
            'received_at' => $this->receivedAt,
            'payment_account_id' => $this->paymentAccountId,
            'reference_number' => $this->referenceNumber,
            'notes' => $this->notes,
        ], fn ($value) => $value !== null);
    }
}
