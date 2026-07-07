<?php

namespace Tests\Unit\Services\Orders;

use App\Models\Event;
use App\Services\Orders\TicketSerialService;
use App\Services\TransactionRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('stress')]
class TicketSerialStressTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_generates_twenty_unique_sequential_serials_for_same_event(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $runner = app(TransactionRunner::class);
        $service = app(TicketSerialService::class);

        $serials = [];

        for ($i = 0; $i < 20; $i++) {
            $serials[] = $runner->run(fn () => $service->nextSerial($event));
        }

        $this->assertSame(
            array_map(fn (int $n) => str_pad((string) $n, 6, '0', STR_PAD_LEFT), range(1, 20)),
            $serials,
        );
        $this->assertSame(20, count(array_unique($serials)));
    }
}
