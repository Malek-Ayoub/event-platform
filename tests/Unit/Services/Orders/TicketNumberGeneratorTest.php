<?php

namespace Tests\Unit\Services\Orders;

use App\Models\Event;
use App\Services\Orders\TicketNumberGenerator;
use App\Services\TransactionRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketNumberGeneratorTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_event_scoped_ticket_numbers_with_expected_format(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'start_datetime' => '2025-07-12 20:00:00',
        ]);

        $runner = app(TransactionRunner::class);
        $first = $runner->run(fn () => app(TicketNumberGenerator::class)->nextForEvent($event));
        $second = $runner->run(fn () => app(TicketNumberGenerator::class)->nextForEvent($event));

        $eventPart = 'EV'.str_pad((string) $event->id, 6, '0', STR_PAD_LEFT);

        $this->assertSame("{$eventPart}-250712-000001", $first);
        $this->assertSame("{$eventPart}-250712-000002", $second);
        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function it_generates_one_thousand_unique_ticket_numbers_for_an_event(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $generator = app(TicketNumberGenerator::class);
        $runner = app(TransactionRunner::class);

        $numbers = [];

        for ($i = 0; $i < 1000; $i++) {
            $numbers[] = $runner->run(fn () => $generator->nextForEvent($event));
        }

        $this->assertCount(1000, $numbers);
        $this->assertSame(1000, count(array_unique($numbers)));
    }
}
