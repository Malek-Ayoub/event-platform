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

final class DecliningPaymentGatewayStub implements PaymentGateway, RefundGateway
{
    public function provider(): string
    {
        return 'shamcash';
    }

    public function initiate(InitiatePaymentRequest $request): InitiatePaymentResponse
    {
        return new InitiatePaymentResponse(
            providerTransactionId: '',
            status: 'failed',
            outcome: GatewayOutcome::Declined,
            redirectUrl: null,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $this->provider(),
                providerStatus: 'failed',
                raw: ['error' => 'Provider declined payment'],
            ),
        );
    }

    public function refund(RefundRequest $request): RefundResponse
    {
        return new RefundResponse(
            providerRefundId: '',
            status: 'failed',
            outcome: GatewayOutcome::Declined,
            providerMetadata: GatewayProviderMetadata::build(
                provider: $this->provider(),
                providerStatus: 'failed',
                raw: ['error' => 'Provider declined refund'],
            ),
        );
    }
}
