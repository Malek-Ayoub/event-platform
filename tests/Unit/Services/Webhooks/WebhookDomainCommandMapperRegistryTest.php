<?php

namespace Tests\Unit\Services\Webhooks;

use App\DTOs\Payments\Gateway\WebhookPayload;
use App\Enums\Webhooks\WebhookEventType;
use App\Exceptions\Webhooks\UnsupportedWebhookEventException;
use App\Services\Webhooks\Mappers\PaymentCompletedMapper;
use App\Services\Webhooks\Mappers\PaymentFailedMapper;
use App\Services\Webhooks\Mappers\RefundProcessedMapper;
use App\Services\Webhooks\WebhookDomainCommandMapperRegistry;
use App\Support\Webhooks\WebhookCorrelation;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookDomainCommandMapperRegistryTest extends TestCase
{
    #[Test]
    public function it_maps_registered_event_types_to_domain_commands(): void
    {
        $registry = new WebhookDomainCommandMapperRegistry([
            'payment.completed' => new PaymentCompletedMapper,
            'payment.failed' => new PaymentFailedMapper,
            'refund.processed' => new RefundProcessedMapper,
        ]);

        $payload = new WebhookPayload(
            provider: 'shamcash',
            providerEventId: 'evt_1',
            rawBody: '{}',
            headers: [],
            parsedPayload: [
                'event_type' => 'payment.completed',
                'provider_transaction_id' => 'TXN-001',
            ],
        );

        $command = $registry->map($payload);

        $this->assertSame(WebhookEventType::PaymentCompleted, $command->eventType);
        $this->assertSame('shamcash', $command->provider);
        $this->assertSame(WebhookCorrelation::id('shamcash', 'evt_1'), $command->correlationId);
        $this->assertSame('TXN-001', $command->payload['provider_transaction_id']);
    }

    #[Test]
    public function it_rejects_unregistered_event_types(): void
    {
        $registry = new WebhookDomainCommandMapperRegistry([
            'payment.completed' => new PaymentCompletedMapper,
        ]);

        $payload = new WebhookPayload(
            provider: 'shamcash',
            providerEventId: 'evt_2',
            rawBody: '{}',
            headers: [],
            parsedPayload: ['event_type' => 'charge.disputed'],
        );

        $this->expectException(UnsupportedWebhookEventException::class);

        $registry->map($payload);
    }
}
