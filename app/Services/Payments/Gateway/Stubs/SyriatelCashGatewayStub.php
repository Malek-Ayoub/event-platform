<?php

namespace App\Services\Payments\Gateway\Stubs;

use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\RefundGateway;
use App\DTOs\Payments\Gateway\InitiatePaymentRequest;
use App\DTOs\Payments\Gateway\InitiatePaymentResponse;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Services\Payments\Gateway\Support\GatewayProviderMetadata;

/** Phase 7.1 stub — no HTTP; kept for isolated tests. */
final class SyriatelCashGatewayStub implements PaymentGateway, RefundGateway
{
    public function provider(): string
    {
        return 'syriatel_cash';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        $transactionId = 'syriatel-stub-'.$request->orderId;

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
                raw: ['stub' => true],
            ),
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        $refundId = $request->providerRefundId ?? 'syriatel-refund-stub-'.$request->providerTransactionId;

        return new RefundResponse(
            providerRefundId: $refundId,
            status: 'pending',
            outcome: GatewayOutcome::Success,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $this->provider(),
                providerTransactionId: $request->providerTransactionId,
                providerReference: $refundId,
                providerStatus: 'pending',
                raw: ['stub' => true],
            ),
        );
    }
}
