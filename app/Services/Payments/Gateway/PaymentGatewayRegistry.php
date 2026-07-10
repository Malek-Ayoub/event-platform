<?php

namespace App\Services\Payments\Gateway;

use App\Contracts\Payments\PaymentVerificationGateway;
use App\Contracts\Payments\RefundGateway;
use App\Exceptions\Payments\Gateway\UnknownPaymentProviderException;

/**
 * Resolves provider-scoped gateway contracts. No business logic (Phase 7.1).
 */
final class PaymentGatewayRegistry
{
    /**
     * @param  array<string, RefundGateway>  $refundGateways
     * @param  array<string, PaymentVerificationGateway>  $verificationGateways
     */
    public function __construct(
        private array $refundGateways,
        private array $verificationGateways = [],
    ) {}

    public function refundGateway(string $provider): RefundGateway
    {
        $key = $this->normalizeProvider($provider);

        if (! isset($this->refundGateways[$key])) {
            throw UnknownPaymentProviderException::forProvider($provider, 'refund gateway');
        }

        return $this->refundGateways[$key];
    }

    public function verificationGateway(string $provider): PaymentVerificationGateway
    {
        $key = $this->normalizeProvider($provider);

        if (! isset($this->verificationGateways[$key])) {
            throw UnknownPaymentProviderException::forProvider($provider, 'verification gateway');
        }

        return $this->verificationGateways[$key];
    }

    /**
     * @return list<string>
     */
    public function registeredProviders(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->refundGateways),
            array_keys($this->verificationGateways),
        )));
    }

    private function normalizeProvider(string $provider): string
    {
        return strtolower(trim($provider));
    }
}
