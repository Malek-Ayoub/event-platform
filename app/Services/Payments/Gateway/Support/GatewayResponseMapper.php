<?php

namespace App\Services\Payments\Gateway\Support;

use App\Contracts\Payments\Http\GatewayHttpResponse;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\DTOs\Payments\Gateway\VerifyTransactionResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Exceptions\Payments\Gateway\GatewayTransportException;
use Throwable;

final class GatewayResponseMapper
{
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

    /**
     * Batch 7.6 — Manual Wallet Transfer. `found` reflects the provider's own
     * lookup result — a technical HTTP failure still yields `outcome !=
     * Success` (handled by the caller via `GatewayOperationException::forVerify`).
     *
     * @param  array<string, mixed>  $raw
     */
    public function verifyTransactionResult(
        bool $found,
        ?string $amount = null,
        ?string $currency = null,
        ?string $receiverAccount = null,
        ?string $providerTransactionId = null,
        ?string $rawStatus = null,
        array $raw = [],
    ): VerifyTransactionResponse {
        return new VerifyTransactionResponse(
            outcome: GatewayOutcome::Success,
            found: $found,
            amount: $amount,
            currency: $currency,
            receiverAccount: $receiverAccount,
            providerTransactionId: $providerTransactionId,
            rawStatus: $rawStatus,
            providerMetadata: ['raw' => $raw],
        );
    }

    public function verifyTransactionTransportFailure(GatewayOutcome $outcome, string $errorMessage, ?int $httpStatus = null): VerifyTransactionResponse
    {
        return new VerifyTransactionResponse(
            outcome: $outcome,
            found: false,
            providerMetadata: array_filter([
                'error' => $errorMessage,
                'http_status' => $httpStatus,
            ], static fn ($value) => $value !== null),
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
