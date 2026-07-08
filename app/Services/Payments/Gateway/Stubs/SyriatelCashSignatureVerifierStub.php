<?php

namespace App\Services\Payments\Gateway\Stubs;

use App\Contracts\Payments\GatewaySignatureVerifier;
use App\DTOs\Payments\Gateway\WebhookPayload;
use App\DTOs\Payments\Gateway\WebhookVerificationResult;

/** Phase 7.1 stub — always defers verification to Phase 7.2. */
final class SyriatelCashSignatureVerifierStub implements GatewaySignatureVerifier
{
    public function provider(): string
    {
        return 'syriatel_cash';
    }

    public function verify(WebhookPayload $payload): WebhookVerificationResult
    {
        return WebhookVerificationResult::success($payload->providerEventId);
    }
}
