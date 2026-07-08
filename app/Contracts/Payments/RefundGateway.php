<?php

namespace App\Contracts\Payments;

use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;

/**
 * External refund provider integration contract (Phase 7.1).
 *
 * Implementations must not access the database or domain services.
 */
interface RefundGateway
{
    public function provider(): string;

    public function refund(RefundRequest $request): RefundResponse;
}
