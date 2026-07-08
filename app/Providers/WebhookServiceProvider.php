<?php

namespace App\Providers;

use App\Services\Payments\PaymentGatewayService;
use App\Services\Webhooks\Mappers\PaymentCompletedMapper;
use App\Services\Webhooks\Mappers\PaymentFailedMapper;
use App\Services\Webhooks\Mappers\RefundProcessedMapper;
use App\Services\Webhooks\ReplayProtectionService;
use App\Services\Webhooks\WebhookDomainCommandMapperRegistry;
use App\Services\Webhooks\WebhookLogService;
use App\Services\Webhooks\WebhookService;
use Illuminate\Support\ServiceProvider;

class WebhookServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WebhookLogService::class);
        $this->app->singleton(ReplayProtectionService::class);

        $this->app->singleton(WebhookDomainCommandMapperRegistry::class, function (): WebhookDomainCommandMapperRegistry {
            $mappers = [
                app(PaymentCompletedMapper::class),
                app(PaymentFailedMapper::class),
                app(RefundProcessedMapper::class),
            ];

            return new WebhookDomainCommandMapperRegistry(
                mappers: array_combine(
                    array_map(static fn ($mapper) => $mapper->eventType()->value, $mappers),
                    $mappers,
                ),
            );
        });

        $this->app->singleton(PaymentGatewayService::class);
        $this->app->singleton(WebhookService::class);
    }
}
