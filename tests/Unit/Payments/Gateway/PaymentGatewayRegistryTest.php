<?php

namespace Tests\Unit\Payments\Gateway;

use App\Contracts\Payments\GatewaySignatureVerifier;
use App\Contracts\Payments\PaymentGateway;
use App\Contracts\Payments\RefundGateway;
use App\DTOs\Payments\Gateway\InitiatePaymentRequest;
use App\DTOs\Payments\Gateway\InitiatePaymentResponse;
use App\DTOs\Payments\Gateway\RefundRequest;
use App\DTOs\Payments\Gateway\RefundResponse;
use App\DTOs\Payments\Gateway\WebhookPayload;
use App\DTOs\Payments\Gateway\WebhookVerificationResult;
use App\Exceptions\Payments\Gateway\UnknownPaymentProviderException;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\Stubs\ShamCashGatewayStub;
use App\Services\Payments\Gateway\Stubs\ShamCashSignatureVerifierStub;
use App\Services\Payments\Gateway\Stubs\SyriatelCashGatewayStub;
use App\Services\Payments\Gateway\Stubs\SyriatelCashSignatureVerifierStub;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

class PaymentGatewayRegistryTest extends TestCase
{
    #[Test]
    public function registry_resolves_payment_gateway_by_provider(): void
    {
        $registry = $this->app->make(PaymentGatewayRegistry::class);

        $gateway = $registry->paymentGateway('shamcash');

        $this->assertInstanceOf(PaymentGateway::class, $gateway);
        $this->assertSame('shamcash', $gateway->provider());
    }

    #[Test]
    public function registry_resolves_refund_gateway_by_provider(): void
    {
        $registry = $this->app->make(PaymentGatewayRegistry::class);

        $gateway = $registry->refundGateway('syriatel_cash');

        $this->assertInstanceOf(RefundGateway::class, $gateway);
        $this->assertSame('syriatel_cash', $gateway->provider());
    }

    #[Test]
    public function registry_resolves_signature_verifier_by_provider(): void
    {
        $registry = $this->app->make(PaymentGatewayRegistry::class);

        $verifier = $registry->signatureVerifier('ShamCash');

        $this->assertInstanceOf(GatewaySignatureVerifier::class, $verifier);
        $this->assertSame('shamcash', $verifier->provider());
    }

    #[Test]
    public function unknown_provider_throws_clear_exception(): void
    {
        $registry = $this->app->make(PaymentGatewayRegistry::class);

        $this->expectException(UnknownPaymentProviderException::class);
        $this->expectExceptionMessage('No payment payment gateway registered for provider [unknown_provider]');

        $registry->paymentGateway('unknown_provider');
    }

    #[Test]
    public function stub_payment_gateway_returns_deterministic_response_without_http(): void
    {
        $gateway = new ShamCashGatewayStub;

        $response = $gateway->initiate(new InitiatePaymentRequest(
            orderId: 42,
            amount: '100.00',
            currency: 'USD',
        ));

        $this->assertSame('shamcash-stub-42', $response->providerTransactionId);
        $this->assertSame('pending', $response->status);
    }

    #[Test]
    public function stub_refund_gateway_returns_deterministic_response(): void
    {
        $gateway = new SyriatelCashGatewayStub;

        $response = $gateway->refund(new RefundRequest(
            providerTransactionId: 'TXN-1',
            amount: '50.00',
            currency: 'USD',
        ));

        $this->assertSame('syriatel-refund-stub-TXN-1', $response->providerRefundId);
        $this->assertSame('pending', $response->status);
    }

    #[Test]
    public function gateway_dtos_are_readonly(): void
    {
        foreach ([
            InitiatePaymentRequest::class,
            InitiatePaymentResponse::class,
            RefundRequest::class,
            RefundResponse::class,
            WebhookPayload::class,
            WebhookVerificationResult::class,
        ] as $class) {
            $reflection = new ReflectionClass($class);
            $this->assertTrue($reflection->isReadOnly(), "{$class} must be readonly");
        }
    }

    #[Test]
    public function container_registers_all_stub_providers(): void
    {
        $registry = $this->app->make(PaymentGatewayRegistry::class);

        $this->assertEqualsCanonicalizing(
            ['shamcash', 'syriatel_cash'],
            $registry->registeredProviders(),
        );

        $this->assertInstanceOf(ShamCashGatewayStub::class, $this->app->make(ShamCashGatewayStub::class));
        $this->assertInstanceOf(SyriatelCashGatewayStub::class, $this->app->make(SyriatelCashGatewayStub::class));
        $this->assertInstanceOf(ShamCashSignatureVerifierStub::class, $this->app->make(ShamCashSignatureVerifierStub::class));
        $this->assertInstanceOf(SyriatelCashSignatureVerifierStub::class, $this->app->make(SyriatelCashSignatureVerifierStub::class));
    }
}
