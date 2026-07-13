<?php

namespace App\Services\Commissions\Data;

use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Models\User;
use Illuminate\Support\Carbon;

readonly class RecordCommissionPaymentData
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function __construct(
        public int $venueId,
        public string $amount,
        public string $currency,
        public CommissionPaymentMethod $paymentMethod,
        public Carbon $receivedAt,
        public User $receivedBy,
        public ?int $paymentAccountId = null,
        public ?string $referenceNumber = null,
        public ?string $notes = null,
        public ?array $metadata = null,
        public ?string $ipAddress = null,
    ) {}
}
