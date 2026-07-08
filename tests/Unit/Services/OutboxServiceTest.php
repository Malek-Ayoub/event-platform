<?php

namespace Tests\Unit\Services;

use App\Models\Event;
use App\Models\Order;
use App\Services\OutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutboxServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_pending_outbox_event_for_aggregate(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $service = app(OutboxService::class);

        $outbox = $service->record(
            eventType: 'order.paid',
            aggregate: $order,
            payload: ['order_id' => $order->id],
        );

        $this->assertSame($venue->id, $outbox->venue_id);
        $this->assertSame('order.paid', $outbox->event_type);
        $this->assertSame(Order::class, $outbox->aggregate_type);
        $this->assertSame($order->id, $outbox->aggregate_id);
        $this->assertSame([
            'aggregate' => 'order',
            'aggregate_id' => $order->id,
            'event' => 'order.paid',
            'version' => 1,
            'payload' => ['order_id' => $order->id],
        ], array_diff_key($outbox->payload, ['occurred_at' => true]));
        $this->assertArrayHasKey('occurred_at', $outbox->payload);
        $this->assertNotEmpty($outbox->payload['occurred_at']);
        $this->assertTrue($outbox->status->value === 'pending');
        $this->assertSame(0, $outbox->attempts);
        $this->assertNull($outbox->processed_at);
    }
}
