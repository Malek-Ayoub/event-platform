<?php

namespace Tests\Unit\Services\Outbox;

use App\Contracts\Outbox\OutboxConsumer;
use App\Enums\FinancialDomain\CommissionStatus;
use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Commission;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\Scopes\BelongsToVenueScope;
use App\Services\Outbox\OutboxConsumerRegistry;
use App\Services\Outbox\OutboxDispatcher;
use App\Services\Outbox\SupportsOutboxEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OutboxDispatcherTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_records_commission_for_order_paid_events(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);
        $venue->update(['commission_rate' => 5.00]);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '120.00',
            'subtotal' => '120.00',
            'status' => OrderStatus::Paid,
        ]);

        $payment = PaymentTransaction::factory()->forOrder($order)->paid()->create([
            'venue_id' => $venue->id,
            'amount' => '120.00',
        ]);

        $outbox = OutboxEvent::factory()->forVenue($venue)->forAggregate($order)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => [
                'aggregate' => 'order',
                'aggregate_id' => $order->id,
                'event' => 'order.paid',
                'version' => 1,
                'occurred_at' => now()->toIso8601String(),
                'payload' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'payment_transaction_id' => $payment->id,
                ],
            ],
        ]);

        $result = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $result->claimed);
        $this->assertSame(1, $result->sent);
        $this->assertSame(OutboxEventStatus::Sent, $outbox->fresh()->status);
        $this->assertNotNull($outbox->fresh()->processed_at);
        $this->assertSame(1, Commission::query()->count());
        $this->assertSame('6.00', Commission::query()->value('amount'));
        $this->assertSame(CommissionStatus::Pending, Commission::query()->first()->status);
    }

    #[Test]
    public function it_marks_unhandled_event_types_as_sent_without_side_effects(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $outbox = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.created',
            'status' => OutboxEventStatus::Pending,
        ]);

        $result = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $result->skipped);
        $this->assertSame(OutboxEventStatus::Sent, $outbox->fresh()->status);
    }

    #[Test]
    public function it_is_idempotent_when_reprocessing_the_same_order_paid_event(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);
        $venue->update(['commission_rate' => 5.00]);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '120.00',
            'subtotal' => '120.00',
            'status' => OrderStatus::Paid,
        ]);

        PaymentTransaction::factory()->forOrder($order)->paid()->create([
            'venue_id' => $venue->id,
            'amount' => '120.00',
        ]);

        OutboxEvent::factory()->forVenue($venue)->forAggregate($order)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => [
                'payload' => [
                    'order_id' => $order->id,
                    'payment_transaction_id' => PaymentTransaction::query()->value('id'),
                ],
            ],
        ]);

        app(OutboxDispatcher::class)->dispatchPending();

        OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->update(['status' => OutboxEventStatus::Pending, 'processed_at' => null]);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, Commission::query()->count());
    }

    #[Test]
    public function it_schedules_retry_before_reaching_max_attempts(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $registry = tap(new OutboxConsumerRegistry, function (OutboxConsumerRegistry $registry): void {
            $registry->register(new class implements OutboxConsumer, SupportsOutboxEventType
            {
                public function eventType(): string
                {
                    return 'order.paid';
                }

                public function supports(string $eventType): bool
                {
                    return $eventType === 'order.paid';
                }

                public function consume(OutboxEvent $event): void
                {
                    throw new RuntimeException('consumer failed');
                }
            });
        });

        $this->app->forgetInstance(OutboxDispatcher::class);
        $this->app->instance(OutboxConsumerRegistry::class, $registry);

        config(['outbox.max_attempts' => 3]);

        $outbox = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => ['payload' => ['order_id' => 1]],
        ]);

        $result = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $result->failed);
        $this->assertSame(OutboxEventStatus::Pending, $outbox->fresh()->status);
        $this->assertSame(1, $outbox->fresh()->attempts);
    }

    #[Test]
    public function it_moves_events_to_failed_after_max_attempts(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $registry = tap(new OutboxConsumerRegistry, function (OutboxConsumerRegistry $registry): void {
            $registry->register(new class implements OutboxConsumer, SupportsOutboxEventType
            {
                public function eventType(): string
                {
                    return 'order.paid';
                }

                public function supports(string $eventType): bool
                {
                    return $eventType === 'order.paid';
                }

                public function consume(OutboxEvent $event): void
                {
                    throw new RuntimeException('consumer failed');
                }
            });
        });

        $this->app->forgetInstance(OutboxDispatcher::class);
        $this->app->instance(OutboxConsumerRegistry::class, $registry);

        config(['outbox.max_attempts' => 1]);

        $outbox = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => ['payload' => ['order_id' => 1]],
        ]);

        $result = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $result->failed);
        $this->assertSame(OutboxEventStatus::Failed, $outbox->fresh()->status);
        $this->assertSame(1, $outbox->fresh()->attempts);
    }
}
