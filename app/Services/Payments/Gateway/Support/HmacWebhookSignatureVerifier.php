<?php

namespace App\Services\Payments\Gateway\Support;

use App\Contracts\Payments\GatewaySignatureVerifier;
use App\DTOs\Payments\Gateway\WebhookPayload;
use App\DTOs\Payments\Gateway\WebhookVerificationResult;
use App\Enums\Payments\GatewayOutcome;

abstract class HmacWebhookSignatureVerifier implements GatewaySignatureVerifier
{
    public function verify(WebhookPayload $payload): WebhookVerificationResult
    {
        $config = GatewayProviderConfig::forProvider($this->provider());

        if ($config->webhookSecret === '') {
            return WebhookVerificationResult::failure(
                reason: 'Webhook secret not configured',
                outcome: GatewayOutcome::ProviderError,
            );
        }

        $signature = $this->extractSignature($payload->headers, $config->signatureHeader);

        if ($signature === null || $signature === '') {
            return WebhookVerificationResult::failure(
                reason: 'Missing signature header',
                outcome: GatewayOutcome::InvalidSignature,
            );
        }

        $expected = hash_hmac('sha256', $payload->rawBody, $config->webhookSecret);

        if (! hash_equals($expected, $this->normalizeSignature($signature))) {
            return WebhookVerificationResult::failure(
                reason: 'Invalid signature',
                outcome: GatewayOutcome::InvalidSignature,
            );
        }

        return WebhookVerificationResult::success(providerEventId: $payload->providerEventId);
    }

    /**
     * @param  array<string, mixed>  $headers
     */
    private function extractSignature(array $headers, string $headerName): ?string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp((string) $key, $headerName) !== 0) {
                continue;
            }

            if (is_array($value)) {
                return isset($value[0]) ? (string) $value[0] : null;
            }

            return (string) $value;
        }

        return null;
    }

    private function normalizeSignature(string $signature): string
    {
        if (str_starts_with(strtolower($signature), 'sha256=')) {
            return substr($signature, 7);
        }

        return $signature;
    }
}
