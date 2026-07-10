<?php

namespace App\Providers;

use App\Contracts\Payments\Http\HttpClientInterface;
use App\Services\Payments\Gateway\ApiSyria\ApiSyriaGateway;
use App\Services\Payments\Gateway\ApiSyria\ApiSyriaHttpClient;
use App\Services\Payments\Gateway\ApiSyria\ApiSyriaProbeService;
use App\Services\Payments\Gateway\ApiSyria\ApiSyriaResponseParser;
use App\Services\Payments\Gateway\Http\Adapters\LaravelHttpClientAdapter;
use App\Services\Payments\Gateway\Http\PaymentGatewayHttpClient;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\Gateway\ShamCash\ShamCashGateway;
use App\Services\Payments\Gateway\Support\GatewayResponseMapper;
use App\Services\Payments\Gateway\SyriatelCash\SyriatelCashGateway;
use App\Services\Payments\Mapping\GatewayPaymentAccountMapper;
use App\Services\Payments\Mapping\RefundRequestMapper;
use App\Services\Payments\Mapping\VerifyTransactionRequestMapper;
use App\Services\Payments\Mapping\VerifyTransactionResponseMapper;
use App\Services\Payments\PaymentAccountGuard;
use App\Services\Payments\PaymentAccountResolver;
use App\Services\Payments\PaymentGatewayService;
use App\Services\Payments\PaymentInstructionService;
use App\Services\Payments\PaymentVerificationService;
use Illuminate\Support\ServiceProvider;

class PaymentGatewayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(HttpClientInterface::class, LaravelHttpClientAdapter::class);
        $this->app->singleton(PaymentGatewayHttpClient::class);
        $this->app->singleton(GatewayResponseMapper::class);

        $this->app->singleton(GatewayPaymentAccountMapper::class);
        $this->app->singleton(RefundRequestMapper::class);
        $this->app->singleton(VerifyTransactionRequestMapper::class);
        $this->app->singleton(VerifyTransactionResponseMapper::class);
        $this->app->singleton(PaymentInstructionService::class);
        $this->app->singleton(PaymentAccountResolver::class);
        $this->app->singleton(PaymentAccountGuard::class);
        $this->app->singleton(PaymentVerificationService::class);
        $this->app->singleton(PaymentGatewayService::class);

        $this->app->singleton(ApiSyriaHttpClient::class);
        $this->app->singleton(ApiSyriaResponseParser::class);
        $this->app->singleton(ApiSyriaProbeService::class);
        $this->app->singleton(ApiSyriaGateway::class);
        $this->app->singleton(ShamCashGateway::class);
        $this->app->singleton(SyriatelCashGateway::class);

        $this->app->singleton(PaymentGatewayRegistry::class, function ($app): PaymentGatewayRegistry {
            $shamCash = $app->make(ShamCashGateway::class);
            $syriatelCash = $app->make(SyriatelCashGateway::class);
            $apiSyria = $app->make(ApiSyriaGateway::class);

            return new PaymentGatewayRegistry(
                refundGateways: [
                    $shamCash->provider() => $shamCash,
                    $syriatelCash->provider() => $syriatelCash,
                ],
                verificationGateways: [
                    $apiSyria->provider() => $apiSyria,
                ],
            );
        });
    }
}
