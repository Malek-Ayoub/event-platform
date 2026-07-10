<?php

namespace App\DTOs\Payments\Gateway;

use App\DTOs\BaseDTO;
use App\Enums\Payments\PaymentWalletProvider;

/**
 * Immutable gateway-layer view of a merchant payment account.
 *
 * Gateways receive this DTO — never Eloquent models or config().
 */
readonly class GatewayPaymentAccount extends BaseDTO
{
    public function __construct(
        public PaymentWalletProvider $provider,
        public string $accountIdentifier,
        public ?string $cashCode = null,
        public ?string $currency = null,
        public ?string $displayName = null,
    ) {}

    public function receiverAccount(): string
    {
        return $this->accountIdentifier;
    }

    public function toArray(): array
    {
        return array_filter([
            'provider' => $this->provider->value,
            'account_identifier' => $this->accountIdentifier,
            'cash_code' => $this->cashCode,
            'currency' => $this->currency,
            'display_name' => $this->displayName,
        ], static fn ($value) => $value !== null);
    }
}
