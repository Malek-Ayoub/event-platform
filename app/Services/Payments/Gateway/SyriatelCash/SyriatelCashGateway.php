<?php

namespace App\Services\Payments\Gateway\SyriatelCash;

use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\RefundGateway;
use App\DTOs\Payments\Gateway\InitiatePaymentRequest;
use App\DTOs\Payments\Gateway\InitiatePaymentResponse;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Services\Payments\Gateway\Http\PaymentGatewayHttpClient;
use App\Services\Payments\Gateway\Support\GatewayProviderConfig;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use Illuminate\Http\Client\Response;
use Throwable;

final class SyriatelCashGateway implements PaymentGateway, RefundGateway
{
    public function __construct(
        private PaymentGatewayHttpClient $http,
        private GatewayResponseMapper $mapper,
    ) {}

    public function provider(): string
    {
        return 'syriatel_cash';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        $config = GatewayProviderConfig::forProvider($this->provider());

        try {
            $response = $this->http->post(
                config: $config,
                path: $config->initiatePath,
                payload: [
                    'external_order_id' => (string) $request->orderId,
                    'amount' => $request->amount,
                    'currency_code' => $request->currency,
                    'custom_data' => $request->metadata,
                ],
            );
        } catch (Throwable $exception) {
            return $this->mapper->initiateFailure(
                request: $request,
                outcome: $this->mapper->classifyTransportException($exception),
                errorMessage: $exception->getMessage(),
            );
        }

        return $this->mapInitiateResponse($response, $request);
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
                request: $request,
                outcome: $this->mapper->classifyTransportException($exception),
                errorMessage: $exception->getMessage(),
            );
        }

        return $this->mapRefundResponse($response, $request);
    }

    private function mapInitiateResponse(Response $response, InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        $body = $response->json();
        $bodyArray = is_array($body) ? $body : null;
        $outcome = $this->mapper->classifyHttpResponse($response, $bodyArray);

        if ($bodyArray === null) {
            return $this->mapper->initiateFailure(
                request: $request,
                outcome: GatewayOutcome::Unknown,
                errorMessage: 'Non-JSON response from Syriatel Cash',
                httpStatus: $response->status(),
            );
        }

        if ($outcome !== GatewayOutcome::Success) {
            return $this->mapper->initiateFailure(
                request: $request,
                outcome: $outcome,
                errorMessage: (string) ($bodyArray['error_message'] ?? $bodyArray['message'] ?? 'Provider rejected payment initiation'),
                providerTransactionId: (string) ($bodyArray['payment_reference'] ?? ''),
                httpStatus: $response->status(),
                extraMetadata: ['raw' => $bodyArray],
            );
        }

        return $this->mapper->initiateSuccess(
            providerTransactionId: (string) ($bodyArray['payment_reference'] ?? ''),
            status: $this->normalizeStatus((string) ($bodyArray['payment_status'] ?? 'pending')),
            redirectUrl: isset($bodyArray['checkout_url']) ? (string) $bodyArray['checkout_url'] : null,
            providerMetadata: isset($bodyArray['provider_data']) && is_array($bodyArray['provider_data']) ? $bodyArray['provider_data'] : [],
        );
    }

    private function mapRefundResponse(Response $response, RefundRequest $request): RefundResponse
    {
        $body = $response->json();
        $bodyArray = is_array($body) ? $body : null;
        $outcome = $this->mapper->classifyHttpResponse($response, $bodyArray);

        if ($bodyArray === null) {
            return $this->mapper->refundFailure(
                request: $request,
                outcome: GatewayOutcome::Unknown,
                errorMessage: 'Non-JSON response from Syriatel Cash',
                httpStatus: $response->status(),
            );
        }

        if ($outcome !== GatewayOutcome::Success) {
            return $this->mapper->refundFailure(
                request: $request,
                outcome: $outcome,
                errorMessage: (string) ($bodyArray['error_message'] ?? $bodyArray['message'] ?? 'Provider rejected refund request'),
                providerRefundId: (string) ($bodyArray['refund_reference'] ?? $request->providerRefundId ?? ''),
                httpStatus: $response->status(),
                extraMetadata: ['raw' => $bodyArray],
            );
        }

        return $this->mapper->refundSuccess(
            providerRefundId: (string) ($bodyArray['refund_reference'] ?? $request->providerRefundId ?? ''),
            status: $this->normalizeStatus((string) ($bodyArray['refund_status'] ?? 'pending')),
            providerMetadata: isset($bodyArray['provider_data']) && is_array($bodyArray['provider_data']) ? $bodyArray['provider_data'] : [],
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
