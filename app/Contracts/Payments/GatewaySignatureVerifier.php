<?php

namespace App\Contracts\Payments;

use App\DTOs\Payments\Gateway\WebhookPayload;
use App\DTOs\Payments\Gateway\WebhookVerificationResult;

/**
 * Provider-specific webhook signature verification (Phase 7.1 contract only).
 *
 * Implementations must not access the database or domain services.
 * Cryptographic verification is implemented in Phase 7.2+.
 */
interface GatewaySignatureVerifier
{
    public function provider(): string;

    public function verify(WebhookPayload $payload): WebhookVerificationResult;
}
