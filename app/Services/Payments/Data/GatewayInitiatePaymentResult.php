<?php

namespace App\Services\Payments\Data;

use App\Models\PaymentTransaction;

readonly class GatewayInitiatePaymentResult
{
    public function __construct(
        public PaymentTransaction $payment,
        public ?string $redirectUrl = null,
    ) {}
}
