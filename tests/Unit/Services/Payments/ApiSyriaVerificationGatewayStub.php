<?php

namespace Tests\Unit\Services\Payments;

use App\Contracts\Payments\PaymentVerificationGateway;
use App\DTOs\Payments\Gateway\VerifyTransactionRequest;
use App\DTOs\Payments\Gateway\VerifyTransactionResponse;
use App\Enums\Payments\GatewayOutcome;

final class ApiSyriaVerificationGatewayStub implements PaymentVerificationGateway
{
    public function __construct(
        private ?VerifyTransactionResponse $response = null,
    ) {}

    public function provider(): string
    {
        return 'apisyria';
    }

    public function verifyTransaction(VerifyTransactionRequest $request): VerifyTransactionResponse
    {
        if ($this->response !== null) {
            return $this->response;
        }

        return new VerifyTransactionResponse(
            outcome: GatewayOutcome::Success,
            found: true,
            amount: $request->expectedAmount,
            currency: $request->expectedCurrency,
            receiverAccount: $request->merchantAccount,
            providerTransactionId: 'APISYRIA-'.$request->transactionNumber,
            rawStatus: 'completed',
        );
    }
}
