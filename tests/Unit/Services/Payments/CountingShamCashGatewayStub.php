<?php

namespace Tests\Unit\Services\Payments;

use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\RefundGateway;
use App\DTOs\Payments\Gateway\InitiatePaymentRequest;
use App\DTOs\Payments\Gateway\InitiatePaymentResponse;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Services\Payments\Gateway\Support\GatewayProviderMetadata;

final class CountingShamCashGatewayStub implements PaymentGateway, RefundGateway
{
    public int $initiateCalls = 0;

    public function provider(): string
    {
        return 'shamcash';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        $this->initiateCalls++;

        $transactionId = 'shamcash-count-'.$request->orderId;

        return new InitiatePaymentResponse(
            providerTransactionId: $transactionId,
            status: 'pending',
            outcome: GatewayOutcome::Success,
            redirectUrl: 'https://pay.example.test/'.$transactionId,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $this->provider(),
                providerTransactionId: $transactionId,
                providerReference: $transactionId,
                providerStatus: 'pending',
            ),
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        return new RefundResponse(
            providerRefundId: 'refund-'.$request->providerTransactionId,
            status: 'pending',
            outcome: GatewayOutcome::Success,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $this->provider(),
                providerTransactionId: $request->providerTransactionId,
                providerReference: 'refund-'.$request->providerTransactionId,
                providerStatus: 'pending',
            ),
        );
    }
}
