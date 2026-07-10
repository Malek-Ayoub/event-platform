<?php

namespace Tests\Unit\Payments\Gateway;

use App\Contracts\Payments\PaymentVerificationGateway;
use App\Contracts\Payments\RefundGateway;
use App\Exceptions\Payments\Gateway\UnknownPaymentProviderException;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Unit\Services\Payments\ApiSyriaVerificationGatewayStub;
use Tests\Unit\Services\Payments\ImmediateRefundGatewayStub;
use Tests\Unit\Services\Payments\ShamCashRefundGatewayStub;

class PaymentGatewayRegistryTest extends TestCase
{
    #[Test]
    public function registry_resolves_refund_gateway_by_provider(): void
    {
        $stub = new ShamCashRefundGatewayStub;
        $registry = new PaymentGatewayRegistry(
            refundGateways: ['shamcash' => $stub],
        );

        $this->assertSame($stub, $registry->refundGateway('shamcash'));
    }

    #[Test]
    public function registry_resolves_verification_gateway_by_provider(): void
    {
        $stub = new ApiSyriaVerificationGatewayStub;
        $registry = new PaymentGatewayRegistry(
            refundGateways: [],
            verificationGateways: ['apisyria' => $stub],
        );

        $this->assertSame($stub, $registry->verificationGateway('apisyria'));
    }

    #[Test]
    public function unknown_provider_throws_clear_exception(): void
    {
        $registry = new PaymentGatewayRegistry(
            refundGateways: ['shamcash' => new ShamCashRefundGatewayStub],
        );

        $this->expectException(UnknownPaymentProviderException::class);

        $registry->refundGateway('unknown');
    }

    #[Test]
    public function container_registers_refund_and_verification_providers(): void
    {
        $registry = app(PaymentGatewayRegistry::class);

        $this->assertContains('shamcash', $registry->registeredProviders());
        $this->assertContains('syriatel_cash', $registry->registeredProviders());
        $this->assertContains('apisyria', $registry->registeredProviders());
    }

    #[Test]
    public function refund_gateway_contract_is_implemented(): void
    {
        $stub = new ImmediateRefundGatewayStub;

        $this->assertInstanceOf(RefundGateway::class, $stub);
    }

    #[Test]
    public function verification_gateway_contract_is_implemented(): void
    {
        $stub = new ApiSyriaVerificationGatewayStub;

        $this->assertInstanceOf(PaymentVerificationGateway::class, $stub);
    }
}
