<?php

namespace App\Services\Payments\Gateway;

use App\Contracts\Payments\GatewaySignatureVerifier;
use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\RefundGateway;
use App\Exceptions\Payments\Gateway\UnknownPaymentProviderException;

/**
 * Resolves provider-scoped gateway contracts. No business logic (Phase 7.1).
 */
final class PaymentGatewayRegistry
{
    /**
     * @param  array<string, PaymentGateway>  $paymentGateways
     * @param  array<string, RefundGateway>  $refundGateways
     * @param  array<string, GatewaySignatureVerifier>  $signatureVerifiers
     */
    public function __construct(
        private array $paymentGateways,
        private array $refundGateways,
        private array $signatureVerifiers,
    ) {}

    public function paymentGateway(string $provider): PaymentGateway
    {
        $key = $this->normalizeProvider($provider);

        if (! isset($this->paymentGateways[$key])) {
            throw UnknownPaymentProviderException::forProvider($provider, 'payment gateway');
        }

        return $this->paymentGateways[$key];
    }

    public function refundGateway(string $provider): RefundGateway
    {
        $key = $this->normalizeProvider($provider);

        if (! isset($this->refundGateways[$key])) {
            throw UnknownPaymentProviderException::forProvider($provider, 'refund gateway');
        }

        return $this->refundGateways[$key];
    }

    public function signatureVerifier(string $provider): GatewaySignatureVerifier
    {
        $key = $this->normalizeProvider($provider);

        if (! isset($this->signatureVerifiers[$key])) {
            throw UnknownPaymentProviderException::forProvider($provider, 'signature verifier');
        }

        return $this->signatureVerifiers[$key];
    }

    /**
     * @return list<string>
     */
    public function registeredProviders(): array
    {
        return array_values(array_unique(array_merge(
            array_keys($this->paymentGateways),
            array_keys($this->refundGateways),
            array_keys($this->signatureVerifiers),
        )));
    }

    private function normalizeProvider(string $provider): string
    {
        return strtolower(trim($provider));
    }
}
