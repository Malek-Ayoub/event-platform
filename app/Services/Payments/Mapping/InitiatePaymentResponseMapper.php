<?php

namespace App\Services\Payments\Mapping;

use App\DTOs\Payments\Gateway\InitiatePaymentResponse;
use App\Services\Payments\Data\InitiatePaymentData;

final class InitiatePaymentResponseMapper
{
    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>
     */
    public function toPayload(InitiatePaymentResponse $response, ?array $metadata = null): array
    {
        $payload = $metadata ?? $response->providerMetadata;

        if ($response->redirectUrl !== null) {
            $payload['redirect_url'] = $response->redirectUrl;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function toDomainData(
        InitiatePaymentResponse $response,
        int $orderId,
        string $provider,
        string $amount,
        string $currency,
        ?array $metadata = null,
    ): InitiatePaymentData {
        return new InitiatePaymentData(
            orderId: $orderId,
            provider: $provider,
            providerTransactionId: $response->providerTransactionId,
            amount: $amount,
            currency: $currency,
            payload: $this->toPayload($response, $metadata),
        );
    }
}
