<?php

namespace App\Services\Payments\Gateway\Stubs;

use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\RefundGateway;
use App\DTOs\Payments\Gateway\InitiatePaymentRequest;
use App\DTOs\Payments\Gateway\InitiatePaymentResponse;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\Enums\Payments\GatewayOutcome;

/** Phase 7.1 stub — no HTTP; replaced in Phase 7.2. */
final class ShamCashGatewayStub implements PaymentGateway, RefundGateway
{
    public function provider(): string
    {
        return 'shamcash';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        return new InitiatePaymentResponse(
            providerTransactionId: 'shamcash-stub-'.$request->orderId,
            status: 'pending',
            outcome: GatewayOutcome::Success,
            redirectUrl: null,
            providerMetadata: ['stub' => true],
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        return new RefundResponse(
            providerRefundId: $request->providerRefundId ?? 'shamcash-refund-stub-'.$request->providerTransactionId,
            status: 'pending',
            outcome: GatewayOutcome::Success,
            providerMetadata: ['stub' => true],
        );
    }
}
