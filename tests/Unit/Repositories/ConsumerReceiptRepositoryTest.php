<?php

namespace Tests\Unit\Repositories;

use App\Models\OutboxConsumerReceipt;
use App\Models\OutboxEvent;
use App\Repositories\ConsumerReceiptRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ConsumerReceiptRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_and_detects_processed_consumers(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.paid',
        ]);

        $repository = app(ConsumerReceiptRepository::class);

        $this->assertFalse($repository->hasProcessed($event->id, 'commission.order_paid'));

        $repository->markProcessed($event->id, 'commission.order_paid');

        $this->assertTrue($repository->hasProcessed($event->id, 'commission.order_paid'));
        $this->assertDatabaseCount('outbox_consumer_receipts', 1);
    }

    #[Test]
    public function mark_processed_is_idempotent_for_the_same_consumer(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.paid',
        ]);

        $repository = app(ConsumerReceiptRepository::class);

        $repository->markProcessed($event->id, 'commission.order_paid');
        $repository->markProcessed($event->id, 'commission.order_paid');

        $this->assertSame(1, OutboxConsumerReceipt::query()->count());
    }
}
