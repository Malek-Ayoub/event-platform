<?php

namespace Tests\Feature\Outbox;

use App\Contracts\Outbox\OutboxConsumer;
use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Models\OutboxConsumerReceipt;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use App\Repositories\ConsumerReceiptRepository;
use App\Services\Outbox\OutboxConsumerRegistry;
use App\Services\Outbox\OutboxDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class OutboxConsumerIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function consumer_receipt_prevents_duplicate_execution(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $outbox = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => ['payload' => ['order_id' => 1]],
        ]);

        $handleCount = 0;
        $registry = $this->registryWithConsumer(
            consumerKey: 'test.order_paid',
            eventType: 'order.paid',
            onHandle: function () use (&$handleCount): void {
                $handleCount++;
            },
        );

        $this->bindRegistry($registry);

        app(OutboxDispatcher::class)->dispatchPending();
        $this->assertSame(1, $handleCount);

        $outbox->update([
            'status' => OutboxEventStatus::Pending,
            'processed_at' => null,
        ]);

        app(OutboxDispatcher::class)->dispatchPending();
        $this->assertSame(1, $handleCount);
    }

    #[Test]
    public function dispatcher_skips_already_processed_consumer(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $outbox = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
        ]);

        app(ConsumerReceiptRepository::class)->markProcessed($outbox->id, 'test.order_paid');

        $handleCount = 0;
        $registry = $this->registryWithConsumer(
            consumerKey: 'test.order_paid',
            eventType: 'order.paid',
            onHandle: function () use (&$handleCount): void {
                $handleCount++;
            },
        );

        $this->bindRegistry($registry);

        $result = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(0, $handleCount);
        $this->assertSame(1, $result->skipped);
        $this->assertSame(OutboxEventStatus::Sent, $outbox->fresh()->status);
    }

    #[Test]
    public function retry_after_partial_failure(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $outbox = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => ['payload' => ['order_id' => 1]],
        ]);

        $firstAttempts = 0;
        $secondAttempts = 0;

        $registry = tap(new OutboxConsumerRegistry, function (OutboxConsumerRegistry $registry) use (&$firstAttempts, &$secondAttempts): void {
            $registry->register($this->countingConsumer('first.order_paid', 'order.paid', $firstAttempts));
            $registry->register($this->failingUntilSecondAttemptConsumer('second.order_paid', 'order.paid', $secondAttempts));
        });

        $this->bindRegistry($registry);

        $firstResult = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $firstResult->failed);
        $this->assertSame(1, $firstAttempts);
        $this->assertSame(1, $secondAttempts);
        $this->assertTrue(app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'first.order_paid'));
        $this->assertFalse(app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'second.order_paid'));
        $this->assertSame(OutboxEventStatus::Pending, $outbox->fresh()->status);

        OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->whereKey($outbox->id)
            ->update(['updated_at' => now()->subHour()]);

        $secondResult = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $secondResult->sent);
        $this->assertSame(1, $firstAttempts);
        $this->assertSame(2, $secondAttempts);
        $this->assertSame(OutboxEventStatus::Sent, $outbox->fresh()->status);
        $this->assertSame(2, OutboxConsumerReceipt::query()->count());
    }

    #[Test]
    public function different_consumers_process_same_event_independently(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $outbox = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
        ]);

        $alphaCount = 0;
        $betaCount = 0;

        $registry = tap(new OutboxConsumerRegistry, function (OutboxConsumerRegistry $registry) use (&$alphaCount, &$betaCount): void {
            $registry->register($this->countingConsumer('alpha.order_paid', 'order.paid', $alphaCount));
            $registry->register($this->countingConsumer('beta.order_paid', 'order.paid', $betaCount));
        });

        $this->bindRegistry($registry);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $alphaCount);
        $this->assertSame(1, $betaCount);
        $this->assertTrue(app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'alpha.order_paid'));
        $this->assertTrue(app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'beta.order_paid'));
    }

    private function bindRegistry(OutboxConsumerRegistry $registry): void
    {
        $this->app->forgetInstance(OutboxDispatcher::class);
        $this->app->forgetInstance(OutboxConsumerRegistry::class);
        $this->app->instance(OutboxConsumerRegistry::class, $registry);
    }

    /**
     * @param  callable(): void  $onHandle
     */
    private function registryWithConsumer(string $consumerKey, string $eventType, callable $onHandle): OutboxConsumerRegistry
    {
        return tap(new OutboxConsumerRegistry, function (OutboxConsumerRegistry $registry) use ($consumerKey, $eventType, $onHandle): void {
            $registry->register(new class($consumerKey, $eventType, $onHandle) implements OutboxConsumer
            {
                public function __construct(
                    private string $consumerKeyValue,
                    private string $eventTypeValue,
                    private $onHandle,
                ) {}

                public function consumerKey(): string
                {
                    return $this->consumerKeyValue;
                }

                public function supports(string $eventType): bool
                {
                    return $eventType === $this->eventTypeValue;
                }

                public function handle(OutboxEvent $event): void
                {
                    ($this->onHandle)();
                }
            });
        });
    }

    private function countingConsumer(string $consumerKey, string $eventType, int &$counter): OutboxConsumer
    {
        return new class($consumerKey, $eventType, $counter) implements OutboxConsumer
        {
            public function __construct(
                private string $consumerKeyValue,
                private string $eventTypeValue,
                private int &$counter,
            ) {}

            public function consumerKey(): string
            {
                return $this->consumerKeyValue;
            }

            public function supports(string $eventType): bool
            {
                return $eventType === $this->eventTypeValue;
            }

            public function handle(OutboxEvent $event): void
            {
                $this->counter++;
            }
        };
    }

    private function failingUntilSecondAttemptConsumer(string $consumerKey, string $eventType, int &$counter): OutboxConsumer
    {
        return new class($consumerKey, $eventType, $counter) implements OutboxConsumer
        {
            public function __construct(
                private string $consumerKeyValue,
                private string $eventTypeValue,
                private int &$counter,
            ) {}

            public function consumerKey(): string
            {
                return $this->consumerKeyValue;
            }

            public function supports(string $eventType): bool
            {
                return $eventType === $this->eventTypeValue;
            }

            public function handle(OutboxEvent $event): void
            {
                $this->counter++;

                if ($this->counter === 1) {
                    throw new RuntimeException('second consumer failed');
                }
            }
        };
    }
}
