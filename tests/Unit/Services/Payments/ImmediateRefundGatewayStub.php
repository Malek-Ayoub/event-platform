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

final class ImmediateRefundGatewayStub implements PaymentGateway, RefundGateway
{
    public function provider(): string
    {
        return 'syriatel_cash';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        $transactionId = 'immediate-stub-'.$request->orderId;

        return new InitiatePaymentResponse(
            providerTransactionId: $transactionId,
            status: 'pending',
            outcome: GatewayOutcome::Success,
            redirectUrl: null,
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
        $refundId = 'immediate-refund-'.$request->providerTransactionId;

        return new RefundResponse(
            providerRefundId: $refundId,
            status: 'completed',
            outcome: GatewayOutcome::Success,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $this->provider(),
                providerTransactionId: $request->providerTransactionId,
                providerReference: $refundId,
                providerStatus: 'completed',
            ),
        );
    }
}
