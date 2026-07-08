<?php

namespace App\Services\Payments\Mapping;

use App\DTOs\Payments\Gateway\InitiatePaymentRequest;

final class InitiatePaymentRequestMapper
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function toGatewayRequest(
        int $orderId,
        string $amount,
        string $currency,
        array $metadata = [],
    ): InitiatePaymentRequest {
        return new InitiatePaymentRequest(
            orderId: $orderId,
            amount: $amount,
            currency: $currency,
            metadata: $metadata,
        );
    }
}
