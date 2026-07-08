<?php

namespace App\Providers;

use App\Contracts\Payments\Http\HttpClientInterface;
use App\Services\Payments\Gateway\Http\Adapters\LaravelHttpClientAdapter;
use App\Services\Payments\Gateway\Http\PaymentGatewayHttpClient;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\ShamCash\ShamCashGateway;
use App\Services\Payments\Gateway\ShamCash\ShamCashSignatureVerifier;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use App\Services\Payments\Gateway\SyriatelCash\SyriatelCashGateway;
use App\Services\Payments\Gateway\SyriatelCash\SyriatelCashSignatureVerifier;
use App\Services\Payments\Mapping\InitiatePaymentRequestMapper;
use App\Services\Payments\Mapping\InitiatePaymentResponseMapper;
use App\Services\Payments\Mapping\RefundRequestMapper;
use App\Services\Payments\PaymentGatewayService;
use Illuminate\Support\ServiceProvider;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HttpClientInterface::class, LaravelHttpClientAdapter::class);
        $this->app->singleton(PaymentGatewayHttpClient::class);
        $this->app->singleton(GatewayResponseMapper::class);

        $this->app->singleton(ShamCashGateway::class);
        $this->app->singleton(SyriatelCashGateway::class);
        $this->app->singleton(ShamCashSignatureVerifier::class);
        $this->app->singleton(SyriatelCashSignatureVerifier::class);
        $this->app->singleton(InitiatePaymentRequestMapper::class);
        $this->app->singleton(InitiatePaymentResponseMapper::class);
        $this->app->singleton(RefundRequestMapper::class);
        $this->app->singleton(PaymentGatewayService::class);

        $this->app->singleton(PaymentGatewayRegistry::class, function ($app): PaymentGatewayRegistry {
            $shamCash = $app->make(ShamCashGateway::class);
            $syriatelCash = $app->make(SyriatelCashGateway::class);

            return new PaymentGatewayRegistry(
                paymentGateways: [
                    $shamCash->provider() => $shamCash,
                    $syriatelCash->provider() => $syriatelCash,
                ],
                refundGateways: [
                    $shamCash->provider() => $shamCash,
                    $syriatelCash->provider() => $syriatelCash,
                ],
                signatureVerifiers: [
                    $app->make(ShamCashSignatureVerifier::class)->provider() => $app->make(ShamCashSignatureVerifier::class),
                    $app->make(SyriatelCashSignatureVerifier::class)->provider() => $app->make(SyriatelCashSignatureVerifier::class),
                ],
            );
        });
    }
}
