<?php

namespace App\Providers;

use App\Repositories\ConsumerReceiptRepository;
use App\Repositories\OutboxRepository;
use App\Services\Outbox\Consumers\GeneratePdfOnQrGeneratedConsumer;
use App\Services\Outbox\Consumers\GenerateQrOnTicketIssuedConsumer;
use App\Services\Outbox\Consumers\IssueTicketsOnOrderPaidConsumer;
use App\Services\Outbox\Consumers\RecordCommissionAdjustmentOnRefundProcessedConsumer;
use App\Services\Outbox\Consumers\RecordCommissionOnOrderPaidConsumer;
use App\Services\Outbox\Consumers\SendOrderPaidEmailConsumer;
use App\Services\Outbox\Consumers\SendTicketEmailConsumer;
use App\Services\Outbox\OutboxConsumerRegistry;
use App\Services\Outbox\OutboxDispatcher;
use App\Services\Outbox\OutboxTenantScope;
use Illuminate\Support\ServiceProvider;

class OutboxServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OutboxRepository::class);
        $this->app->singleton(ConsumerReceiptRepository::class);
        $this->app->singleton(OutboxTenantScope::class);

        $this->app->singleton(OutboxConsumerRegistry::class, function ($app): OutboxConsumerRegistry {
            $registry = new OutboxConsumerRegistry;
            $registry->register($app->make(IssueTicketsOnOrderPaidConsumer::class));
            $registry->register($app->make(GenerateQrOnTicketIssuedConsumer::class));
            $registry->register($app->make(GeneratePdfOnQrGeneratedConsumer::class));
            $registry->register($app->make(RecordCommissionOnOrderPaidConsumer::class));
            $registry->register($app->make(SendOrderPaidEmailConsumer::class));
            $registry->register($app->make(SendTicketEmailConsumer::class));
            $registry->register($app->make(RecordCommissionAdjustmentOnRefundProcessedConsumer::class));

            return $registry;
        });

        $this->app->singleton(OutboxDispatcher::class);
    }
}
