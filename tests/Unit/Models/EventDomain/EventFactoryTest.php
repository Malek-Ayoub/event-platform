<?php

namespace Tests\Unit\Models\EventDomain;

use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventFactoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function factory_creates_category_by_default(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);

        $this->assertNotNull($event->category_id);
        $this->assertSame($venue->id, $event->category->venue_id);
    }

    #[Test]
    public function without_category_state_skips_auto_category_creation(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->withoutCategory()->create(['venue_id' => $venue->id]);

        $this->assertNull($event->category_id);
    }
}
