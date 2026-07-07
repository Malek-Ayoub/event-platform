<?php

namespace Tests\Unit\Models\EventDomain;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventSoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function event_supports_soft_deletes(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $eventId = $event->id;

        $event->delete();

        $this->assertSoftDeleted('events', ['id' => $eventId]);
        $this->assertNull(Event::query()->find($eventId));
        $this->assertNotNull(Event::withTrashed()->find($eventId));
    }
}
