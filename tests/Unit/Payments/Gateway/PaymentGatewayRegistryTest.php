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
use App\Enums\Payments\GatewayOutcome;
use App\Exceptions\Payments\Gateway\UnknownPaymentProviderException;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\ShamCash\ShamCashGateway;
use App\Services\Payments\Gateway\ShamCash\ShamCashSignatureVerifier;
use App\Services\Payments\Gateway\Stubs\ShamCashGatewayStub;
use App\Services\Payments\Gateway\Stubs\SyriatelCashGatewayStub;
use App\Services\Payments\Gateway\Support\GatewayProviderMetadata;
use App\Services\Payments\Gateway\SyriatelCash\SyriatelCashGateway;
use App\Services\Payments\Gateway\SyriatelCash\SyriatelCashSignatureVerifier;
use Illuminate\Support\Facades\Http;
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
    public function container_registers_all_gateway_providers(): void
    {
        $registry = $this->app->make(PaymentGatewayRegistry::class);

        $this->assertEqualsCanonicalizing(
            ['shamcash', 'syriatel_cash'],
            $registry->registeredProviders(),
        );

        $this->assertInstanceOf(ShamCashGateway::class, $this->app->make(ShamCashGateway::class));
        $this->assertInstanceOf(SyriatelCashGateway::class, $this->app->make(SyriatelCashGateway::class));
        $this->assertInstanceOf(ShamCashSignatureVerifier::class, $this->app->make(ShamCashSignatureVerifier::class));
        $this->assertInstanceOf(SyriatelCashSignatureVerifier::class, $this->app->make(SyriatelCashSignatureVerifier::class));
    }

    #[Test]
    public function shamcash_gateway_maps_successful_initiate_response(): void
    {
        config([
            'payment_gateways.providers.shamcash.base_url' => 'https://api.shamcash.test',
            'payment_gateways.providers.shamcash.api_key' => 'test-key',
            'payment_gateways.providers.shamcash.initiate_path' => '/v1/payments',
        ]);

        Http::fake([
            'https://api.shamcash.test/v1/payments' => Http::response([
                'transaction_id' => 'SC-TXN-100',
                'status' => 'pending',
                'redirect_url' => 'https://pay.shamcash.test/checkout/100',
                'meta' => ['channel' => 'mobile'],
            ], 201),
        ]);

        $gateway = $this->app->make(ShamCashGateway::class);

        $response = $gateway->initiate(new InitiatePaymentRequest(
            orderId: 100,
            amount: '250.00',
            currency: 'USD',
        ));

        $this->assertSame('SC-TXN-100', $response->providerTransactionId);
        $this->assertSame('pending', $response->status);
        $this->assertSame(GatewayOutcome::Success, $response->outcome);
        $this->assertSame('https://pay.shamcash.test/checkout/100', $response->redirectUrl);
        $this->assertSame('shamcash', $response->providerMetadata[GatewayProviderMetadata::PROVIDER]);
        $this->assertSame('SC-TXN-100', $response->providerMetadata[GatewayProviderMetadata::PROVIDER_TRANSACTION_ID]);
        $this->assertSame('pending', $response->providerMetadata[GatewayProviderMetadata::PROVIDER_STATUS]);
        $this->assertSame([
            'transaction_id' => 'SC-TXN-100',
            'status' => 'pending',
            'redirect_url' => 'https://pay.shamcash.test/checkout/100',
            'meta' => ['channel' => 'mobile'],
        ], $response->providerMetadata[GatewayProviderMetadata::RAW]);
    }

    #[Test]
    public function shamcash_gateway_maps_provider_failure_to_failed_dto(): void
    {
        config([
            'payment_gateways.providers.shamcash.base_url' => 'https://api.shamcash.test',
            'payment_gateways.providers.shamcash.api_key' => 'test-key',
            'payment_gateways.providers.shamcash.initiate_path' => '/v1/payments',
        ]);

        Http::fake([
            'https://api.shamcash.test/v1/payments' => Http::response([
                'transaction_id' => 'SC-TXN-ERR',
                'message' => 'Insufficient merchant balance',
            ], 422),
        ]);

        $gateway = $this->app->make(ShamCashGateway::class);

        $response = $gateway->initiate(new InitiatePaymentRequest(
            orderId: 101,
            amount: '10.00',
            currency: 'USD',
        ));

        $this->assertSame('failed', $response->status);
        $this->assertSame(GatewayOutcome::Declined, $response->outcome);
        $this->assertSame('SC-TXN-ERR', $response->providerTransactionId);
        $this->assertSame('Insufficient merchant balance', $response->providerMetadata[GatewayProviderMetadata::RAW]['error']);
    }

    #[Test]
    public function syriatel_cash_gateway_maps_successful_refund_response(): void
    {
        config([
            'payment_gateways.providers.syriatel_cash.base_url' => 'https://api.syriatel.test',
            'payment_gateways.providers.syriatel_cash.api_key' => 'test-key',
            'payment_gateways.providers.syriatel_cash.refund_path' => '/api/payment/refund',
        ]);

        Http::fake([
            'https://api.syriatel.test/api/payment/refund' => Http::response([
                'refund_reference' => 'SY-REF-55',
                'refund_status' => 'success',
                'provider_data' => ['batch' => 'B-1'],
            ], 200),
        ]);

        $gateway = $this->app->make(SyriatelCashGateway::class);

        $response = $gateway->refund(new RefundRequest(
            providerTransactionId: 'SY-PAY-10',
            amount: '25.00',
            currency: 'USD',
        ));

        $this->assertSame('SY-REF-55', $response->providerRefundId);
        $this->assertSame('completed', $response->status);
        $this->assertSame(GatewayOutcome::Success, $response->outcome);
        $this->assertSame('syriatel_cash', $response->providerMetadata[GatewayProviderMetadata::PROVIDER]);
        $this->assertSame([
            'refund_reference' => 'SY-REF-55',
            'refund_status' => 'success',
            'provider_data' => ['batch' => 'B-1'],
        ], $response->providerMetadata[GatewayProviderMetadata::RAW]);
    }

    #[Test]
    public function shamcash_signature_verifier_validates_hmac_signature(): void
    {
        config(['payment_gateways.providers.shamcash.webhook_secret' => 'whsec_test']);

        $rawBody = '{"event_id":"evt_1","status":"paid"}';
        $signature = hash_hmac('sha256', $rawBody, 'whsec_test');

        $verifier = $this->app->make(ShamCashSignatureVerifier::class);

        $result = $verifier->verify(new WebhookPayload(
            provider: 'shamcash',
            providerEventId: 'evt_1',
            rawBody: $rawBody,
            headers: ['X-ShamCash-Signature' => $signature],
            parsedPayload: ['event_id' => 'evt_1', 'status' => 'paid'],
        ));

        $this->assertTrue($result->verified);
        $this->assertSame(GatewayOutcome::Success, $result->outcome);
        $this->assertSame('evt_1', $result->providerEventId);
    }

    #[Test]
    public function syriatel_cash_signature_verifier_rejects_invalid_signature(): void
    {
        config(['payment_gateways.providers.syriatel_cash.webhook_secret' => 'whsec_syriatel']);

        $verifier = $this->app->make(SyriatelCashSignatureVerifier::class);

        $result = $verifier->verify(new WebhookPayload(
            provider: 'syriatel_cash',
            providerEventId: 'evt_2',
            rawBody: '{"event_id":"evt_2"}',
            headers: ['X-Syriatel-Signature' => 'invalid'],
            parsedPayload: ['event_id' => 'evt_2'],
        ));

        $this->assertFalse($result->verified);
        $this->assertSame(GatewayOutcome::InvalidSignature, $result->outcome);
        $this->assertSame('Invalid signature', $result->failureReason);
    }
}
