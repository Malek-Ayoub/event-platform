<?php

namespace Tests\Unit\Models\EventDomain;

use App\Exceptions\StaleModelException;
use App\Models\Event;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventDomainOptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function event_update_with_version_increments_version(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id, 'version' => 1]);

        $event->updateWithVersion(['name' => 'Updated Event'], 1);

        $this->assertSame('Updated Event', $event->fresh()->name);
        $this->assertSame(2, $event->fresh()->version);
    }

    #[Test]
    public function event_update_with_version_throws_on_conflict(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id, 'version' => 1]);

        Event::query()->whereKey($event->id)->update(['version' => 2]);

        $this->expectException(StaleModelException::class);

        $event->updateWithVersion(['name' => 'Conflict'], 1);
    }

    #[Test]
    public function ticket_type_update_with_version_increments_version(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create(['version' => 1]);

        $ticketType->updateWithVersion(['name' => 'VIP Updated'], 1);

        $this->assertSame('VIP Updated', $ticketType->fresh()->name);
        $this->assertSame(2, $ticketType->fresh()->version);
    }
}
