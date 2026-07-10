<?php

namespace Tests\Unit\Services\Payments;

use App\Contracts\Payments\RefundGateway;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\Enums\Payments\GatewayOutcome;
use App\Services\Payments\Gateway\Support\GatewayProviderMetadata;

final class ImmediateRefundGatewayStub implements RefundGateway
{
    public function provider(): string
    {
        return 'syriatel_cash';
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
