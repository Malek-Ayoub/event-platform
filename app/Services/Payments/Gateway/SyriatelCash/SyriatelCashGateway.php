<?php

namespace App\Services\Payments\Gateway\SyriatelCash;

use App\Contracts\Payments\Http\GatewayHttpResponse;
use App\Contracts\Payments\RefundGateway;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Services\Payments\Gateway\Http\PaymentGatewayHttpClient;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use Throwable;

final class SyriatelCashGateway implements RefundGateway
{
    public function __construct(
        private PaymentGatewayHttpClient $http,
        private GatewayResponseMapper $mapper,
    ) {}

    public function provider(): string
    {
        return 'syriatel_cash';
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $config = GatewayProviderConfig::forProvider($this->provider());

        $payload = [
            'payment_reference' => $request->providerTransactionId,
            'amount' => $request->amount,
            'currency_code' => $request->currency,
            'custom_data' => $request->metadata,
        ];

        if ($request->reason !== null) {
            $payload['refund_reason'] = $request->reason;
        }

        if ($request->providerRefundId !== null) {
            $payload['refund_reference'] = $request->providerRefundId;
        }

        try {
            $response = $this->http->post(
                config: $config,
                path: $config->refundPath,
                payload: $payload,
            );
        } catch (Throwable $exception) {
            return $this->mapper->refundFailure(
                provider: $this->provider(),
                request: $request,
                outcome: $this->mapper->classifyTransportException($exception),
                errorMessage: $exception->getMessage(),
            );
        }

        return $this->mapRefundResponse($response, $request);
    }

    private function mapRefundResponse(GatewayHttpResponse $response, RefundRequest $request): RefundResponse
    {
        $bodyArray = $response->body;
        $outcome = $this->mapper->classifyHttpResponse($response, $bodyArray);

        if ($bodyArray === null) {
            return $this->mapper->refundFailure(
                provider: $this->provider(),
                request: $request,
                outcome: GatewayOutcome::Unknown,
                errorMessage: 'Non-JSON response from Syriatel Cash',
                httpStatus: $response->status,
            );
        }

        if ($outcome !== GatewayOutcome::Success) {
            return $this->mapper->refundFailure(
                provider: $this->provider(),
                request: $request,
                outcome: $outcome,
                errorMessage: (string) ($bodyArray['error_message'] ?? $bodyArray['message'] ?? 'Provider rejected refund request'),
                providerRefundId: (string) ($bodyArray['refund_reference'] ?? $request->providerRefundId ?? ''),
                providerStatus: (string) ($bodyArray['refund_status'] ?? 'failed'),
                httpStatus: $response->status,
                raw: $bodyArray,
            );
        }

        $providerStatus = (string) ($bodyArray['refund_status'] ?? 'pending');

        return $this->mapper->refundSuccess(
            provider: $this->provider(),
            providerRefundId: (string) ($bodyArray['refund_reference'] ?? $request->providerRefundId ?? ''),
            providerStatus: $this->normalizeStatus($providerStatus),
            providerTransactionId: $request->providerTransactionId,
            raw: $bodyArray,
        );
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower($status)) {
            'success', 'completed', 'paid' => 'completed',
            'error', 'declined', 'rejected' => 'failed',
            default => strtolower($status),
        };
    }
}
