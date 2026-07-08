<?php

namespace App\Services\Payments\Gateway\Support;

use App\DTOs\Payments\Gateway\InitiatePaymentRequest;
use App\DTOs\Payments\Gateway\InitiatePaymentResponse;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\Enums\Payments\GatewayOutcome;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Throwable;

final class GatewayResponseMapper
{
    public function initiateSuccess(
        string $providerTransactionId,
        string $status,
        ?string $redirectUrl = null,
        array $providerMetadata = [],
    ): InitiatePaymentResponse {
        return new InitiatePaymentResponse(
            providerTransactionId: $providerTransactionId,
            status: $status,
            outcome: GatewayOutcome::Success,
            redirectUrl: $redirectUrl,
            providerMetadata: $providerMetadata,
        );
    }

    public function initiateFailure(
        InitiatePaymentRequest $request,
        GatewayOutcome $outcome,
        string $errorMessage,
        string $providerTransactionId = '',
        ?int $httpStatus = null,
        array $extraMetadata = [],
    ): InitiatePaymentResponse {
        return new InitiatePaymentResponse(
            providerTransactionId: $providerTransactionId,
            status: 'failed',
            outcome: $outcome,
            redirectUrl: null,
            providerMetadata: array_filter(array_merge([
                'error' => $errorMessage,
                'order_id' => $request->orderId,
                'http_status' => $httpStatus,
            ], $extraMetadata), static fn ($value) => $value !== null),
        );
    }

    public function refundSuccess(
        string $providerRefundId,
        string $status,
        array $providerMetadata = [],
    ): RefundResponse {
        return new RefundResponse(
            providerRefundId: $providerRefundId,
            status: $status,
            outcome: GatewayOutcome::Success,
            providerMetadata: $providerMetadata,
        );
    }

    public function refundFailure(
        RefundRequest $request,
        GatewayOutcome $outcome,
        string $errorMessage,
        string $providerRefundId = '',
        ?int $httpStatus = null,
        array $extraMetadata = [],
    ): RefundResponse {
        return new RefundResponse(
            providerRefundId: $providerRefundId !== '' ? $providerRefundId : ($request->providerRefundId ?? ''),
            status: 'failed',
            outcome: $outcome,
            providerMetadata: array_filter(array_merge([
                'error' => $errorMessage,
                'provider_transaction_id' => $request->providerTransactionId,
                'http_status' => $httpStatus,
            ], $extraMetadata), static fn ($value) => $value !== null),
        );
    }

    public function classifyHttpResponse(Response $response, ?array $body): GatewayOutcome
    {
        if (! is_array($body)) {
            return GatewayOutcome::Unknown;
        }

        if ($response->successful()) {
            return GatewayOutcome::Success;
        }

        if (in_array($response->status(), [408, 504, 524], true)) {
            return GatewayOutcome::Timeout;
        }

        if ($response->status() >= 500) {
            return GatewayOutcome::ProviderError;
        }

        if (in_array($response->status(), [402, 422], true)) {
            return GatewayOutcome::Declined;
        }

        return GatewayOutcome::ProviderError;
    }

    public function classifyTransportException(Throwable $exception): GatewayOutcome
    {
        if ($exception instanceof ConnectionException) {
            if ($this->isTimeoutMessage($exception->getMessage())) {
                return GatewayOutcome::Timeout;
            }

            return GatewayOutcome::NetworkError;
        }

        if ($this->isTimeoutMessage($exception->getMessage())) {
            return GatewayOutcome::Timeout;
        }

        return GatewayOutcome::NetworkError;
    }

    private function isTimeoutMessage(string $message): bool
    {
        $normalized = strtolower($message);

        return str_contains($normalized, 'timeout')
            || str_contains($normalized, 'timed out');
    }
}
