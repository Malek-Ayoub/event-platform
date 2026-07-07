<?php

namespace Tests\Unit\Services\Orders;

use App\Models\Event;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\TicketSerialCounter;
use App\Services\Orders\TicketSerialService;
use App\Services\TransactionRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TicketSerialServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_increments_serial_correctly(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $service = app(TicketSerialService::class);
        $runner = app(TransactionRunner::class);

        $serials = $runner->run(fn () => [
            $service->nextSerial($event),
            $service->nextSerial($event),
            $service->nextSerial($event),
        ]);

        $this->assertSame(['000001', '000002', '000003'], $serials);

        $counter = TicketSerialCounter::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('event_id', $event->id)
            ->first();

        $this->assertSame(3, $counter->last_serial);
    }

    #[Test]
    public function it_prevents_duplicate_serials_within_a_transaction(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $service = app(TicketSerialService::class);
        $runner = app(TransactionRunner::class);

        $serials = $runner->run(fn () => [
            $service->nextSerial($event),
            $service->nextSerial($event),
        ]);

        $this->assertCount(2, array_unique($serials));
    }

    #[Test]
    public function it_maintains_separate_counters_per_event(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $eventA = Event::factory()->create(['venue_id' => $venue->id]);
        $eventB = Event::factory()->create(['venue_id' => $venue->id]);
        $service = app(TicketSerialService::class);
        $runner = app(TransactionRunner::class);

        $runner->run(function () use ($service, $eventA, $eventB): void {
            $this->assertSame('000001', $service->nextSerial($eventA));
            $this->assertSame('000001', $service->nextSerial($eventB));
            $this->assertSame('000002', $service->nextSerial($eventA));
        });
    }

    #[Test]
    public function it_maintains_separate_counters_per_venue(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();

        $this->bindTenant($venueA->id);
        $eventA = Event::factory()->create(['venue_id' => $venueA->id]);

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->create(['venue_id' => $venueB->id]);

        $service = app(TicketSerialService::class);
        $runner = app(TransactionRunner::class);

        $this->bindTenant($venueA->id);
        $runner->run(fn () => $this->assertSame('000001', $service->nextSerial($eventA)));

        $this->bindTenant($venueB->id);
        $runner->run(fn () => $this->assertSame('000001', $service->nextSerial($eventB)));
    }

    #[Test]
    public function rollback_restores_counter_state(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $service = app(TicketSerialService::class);
        $runner = app(TransactionRunner::class);

        try {
            $runner->run(function () use ($service, $event): void {
                $service->nextSerial($event);
                $service->nextSerial($event);

                throw new RuntimeException('rollback');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertNull(
            TicketSerialCounter::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('event_id', $event->id)
                ->first(),
        );
    }
}
