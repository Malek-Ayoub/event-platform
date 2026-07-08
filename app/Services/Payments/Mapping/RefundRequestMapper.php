<?php

namespace App\Services\Payments\Mapping;

use App\DTOs\Payments\Gateway\RefundRequest;

final class RefundRequestMapper
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function toGatewayRequest(
        string $providerTransactionId,
        string $amount,
        string $currency,
        ?string $reason = null,
        ?string $providerRefundId = null,
        array $metadata = [],
    ): RefundRequest {
        return new RefundRequest(
            providerTransactionId: $providerTransactionId,
            amount: $amount,
            currency: $currency,
            reason: $reason,
            providerRefundId: $providerRefundId,
            metadata: $metadata,
        );
    }
}
