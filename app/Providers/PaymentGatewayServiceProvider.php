<?php

namespace App\Providers;

use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\Stubs\ShamCashGatewayStub;
use App\Services\Payments\Gateway\Stubs\ShamCashSignatureVerifierStub;
use App\Services\Payments\Gateway\Stubs\SyriatelCashGatewayStub;
use App\Services\Payments\Gateway\Stubs\SyriatelCashSignatureVerifierStub;
use Illuminate\Support\ServiceProvider;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ShamCashGatewayStub::class);
        $this->app->singleton(SyriatelCashGatewayStub::class);
        $this->app->singleton(ShamCashSignatureVerifierStub::class);
        $this->app->singleton(SyriatelCashSignatureVerifierStub::class);

        $this->app->singleton(PaymentGatewayRegistry::class, function ($app): PaymentGatewayRegistry {
            $shamCash = $app->make(ShamCashGatewayStub::class);
            $syriatelCash = $app->make(SyriatelCashGatewayStub::class);

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
                    $app->make(ShamCashSignatureVerifierStub::class)->provider() => $app->make(ShamCashSignatureVerifierStub::class),
                    $app->make(SyriatelCashSignatureVerifierStub::class)->provider() => $app->make(SyriatelCashSignatureVerifierStub::class),
                ],
            );
        });
    }
}
