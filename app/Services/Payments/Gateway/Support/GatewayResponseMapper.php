<?php

namespace App\Services\Payments\Gateway\Support;

use App\Contracts\Payments\Http\GatewayHttpResponse;
use App\DTOs\Payments\Gateway\InitiatePaymentRequest;
use App\DTOs\Payments\Gateway\InitiatePaymentResponse;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Exceptions\Payments\Gateway\GatewayTransportException;
use Throwable;

final class GatewayResponseMapper
{
    public function initiateSuccess(
        string $provider,
        string $providerTransactionId,
        string $providerStatus,
        ?string $providerReference = null,
        ?string $redirectUrl = null,
        array $raw = [],
    ): InitiatePaymentResponse {
        return new InitiatePaymentResponse(
            providerTransactionId: $providerTransactionId,
            status: $providerStatus,
            outcome: GatewayOutcome::Success,
            redirectUrl: $redirectUrl,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $provider,
                providerTransactionId: $providerTransactionId,
                providerReference: $providerReference ?? $providerTransactionId,
                providerStatus: $providerStatus,
                raw: $raw,
            ),
        );
    }

    public function initiateFailure(
        string $provider,
        InitiatePaymentRequest $request,
        GatewayOutcome $outcome,
        string $errorMessage,
        string $providerTransactionId = '',
        ?string $providerReference = null,
        ?string $providerStatus = null,
        ?int $httpStatus = null,
        array $raw = [],
    ): InitiatePaymentResponse {
        return new InitiatePaymentResponse(
            providerTransactionId: $providerTransactionId,
            status: 'failed',
            outcome: $outcome,
            redirectUrl: null,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $provider,
                providerTransactionId: $providerTransactionId,
                providerReference: $providerReference ?? (string) $request->orderId,
                providerStatus: $providerStatus ?? 'failed',
                raw: array_filter([
                    'error' => $errorMessage,
                    'order_id' => $request->orderId,
                    'http_status' => $httpStatus,
                    'response' => $raw,
                ], static fn ($value) => $value !== null),
            ),
        );
    }

    public function refundSuccess(
        string $provider,
        string $providerRefundId,
        string $providerStatus,
        string $providerTransactionId,
        array $raw = [],
    ): RefundResponse {
        return new RefundResponse(
            providerRefundId: $providerRefundId,
            status: $providerStatus,
            outcome: GatewayOutcome::Success,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $provider,
                providerTransactionId: $providerTransactionId,
                providerReference: $providerRefundId,
                providerStatus: $providerStatus,
                raw: $raw,
            ),
        );
    }

    public function refundFailure(
        string $provider,
        RefundRequest $request,
        GatewayOutcome $outcome,
        string $errorMessage,
        string $providerRefundId = '',
        ?string $providerStatus = null,
        ?int $httpStatus = null,
        array $raw = [],
    ): RefundResponse {
        return new RefundResponse(
            providerRefundId: $providerRefundId !== '' ? $providerRefundId : ($request->providerRefundId ?? ''),
            status: 'failed',
            outcome: $outcome,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $provider,
                providerTransactionId: $request->providerTransactionId,
                providerReference: $providerRefundId !== '' ? $providerRefundId : ($request->providerRefundId ?? ''),
                providerStatus: $providerStatus ?? 'failed',
                raw: array_filter([
                    'error' => $errorMessage,
                    'http_status' => $httpStatus,
                    'response' => $raw,
                ], static fn ($value) => $value !== null),
            ),
        );
    }

    public function classifyHttpResponse(GatewayHttpResponse $response, ?array $body): GatewayOutcome
    {
        if (! is_array($body)) {
            return GatewayOutcome::Unknown;
        }

        if ($response->successful()) {
            return GatewayOutcome::Success;
        }

        if (in_array($response->status, [408, 504, 524], true)) {
            return GatewayOutcome::Timeout;
        }

        if ($response->status >= 500) {
            return GatewayOutcome::ProviderError;
        }

        if (in_array($response->status, [402, 422], true)) {
            return GatewayOutcome::Declined;
        }

        return GatewayOutcome::ProviderError;
    }

    public function classifyTransportException(Throwable $exception): GatewayOutcome
    {
        if ($exception instanceof GatewayTransportException) {
            return $exception->outcome;
        }

        return GatewayOutcome::NetworkError;
    }
}
