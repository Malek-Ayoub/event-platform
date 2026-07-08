<?php

namespace App\Services\Payments\Gateway\ShamCash;

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

final class ShamCashGateway implements PaymentGateway, RefundGateway
{
    public function __construct(
        private PaymentGatewayHttpClient $http,
        private GatewayResponseMapper $mapper,
    ) {}

    public function provider(): string
    {
        return 'shamcash';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        $config = GatewayProviderConfig::forProvider($this->provider());

        try {
            $response = $this->http->post(
                config: $config,
                path: $config->initiatePath,
                payload: [
                    'order_id' => (string) $request->orderId,
                    'amount' => $request->amount,
                    'currency' => $request->currency,
                    'metadata' => $request->metadata,
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
            'transaction_id' => $request->providerTransactionId,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'metadata' => $request->metadata,
        ];

        if ($request->reason !== null) {
            $payload['reason'] = $request->reason;
        }

        if ($request->providerRefundId !== null) {
            $payload['refund_id'] = $request->providerRefundId;
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
                errorMessage: 'Non-JSON response from ShamCash',
                httpStatus: $response->status(),
            );
        }

        if ($outcome !== GatewayOutcome::Success) {
            return $this->mapper->initiateFailure(
                request: $request,
                outcome: $outcome,
                errorMessage: (string) ($bodyArray['message'] ?? $bodyArray['error'] ?? 'Provider rejected payment initiation'),
                providerTransactionId: (string) ($bodyArray['transaction_id'] ?? ''),
                httpStatus: $response->status(),
                extraMetadata: ['raw' => $bodyArray],
            );
        }

        return $this->mapper->initiateSuccess(
            providerTransactionId: (string) ($bodyArray['transaction_id'] ?? ''),
            status: (string) ($bodyArray['status'] ?? 'pending'),
            redirectUrl: isset($bodyArray['redirect_url']) ? (string) $bodyArray['redirect_url'] : null,
            providerMetadata: isset($bodyArray['meta']) && is_array($bodyArray['meta']) ? $bodyArray['meta'] : [],
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
                errorMessage: 'Non-JSON response from ShamCash',
                httpStatus: $response->status(),
            );
        }

        if ($outcome !== GatewayOutcome::Success) {
            return $this->mapper->refundFailure(
                request: $request,
                outcome: $outcome,
                errorMessage: (string) ($bodyArray['message'] ?? $bodyArray['error'] ?? 'Provider rejected refund request'),
                providerRefundId: (string) ($bodyArray['refund_id'] ?? $request->providerRefundId ?? ''),
                httpStatus: $response->status(),
                extraMetadata: ['raw' => $bodyArray],
            );
        }

        return $this->mapper->refundSuccess(
            providerRefundId: (string) ($bodyArray['refund_id'] ?? $request->providerRefundId ?? ''),
            status: (string) ($bodyArray['status'] ?? 'pending'),
            providerMetadata: isset($bodyArray['meta']) && is_array($bodyArray['meta']) ? $bodyArray['meta'] : [],
        );
    }
}
