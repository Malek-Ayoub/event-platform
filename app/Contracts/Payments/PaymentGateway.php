<?php

namespace App\Contracts\Payments;

use App\DTOs\Payments\Gateway\InitiatePaymentRequest;
use App\DTOs\Payments\Gateway\InitiatePaymentResponse;

/**
 * External payment provider integration contract (Phase 7.1).
 *
 * Implementations must not access the database or domain services.
 */
interface PaymentGateway
{
    public function provider(): string;

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResponse;
}
