<?php

namespace Tests\Unit\Services\Dashboard;

use App\Enums\EventDomain\EventStatus;
use App\Models\Event;
use App\Models\TicketType;
use App\Models\Venue;
use App\Services\Dashboard\OrganizerDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizerDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_upcoming_events_with_capacity_and_remaining_tickets(): void
    {
        $venue = Venue::factory()->create();
        $this->bindTenant($venue->id);

        $upcoming = Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Future Event',
            'status' => EventStatus::Published,
            'start_datetime' => Carbon::parse('2099-06-01 18:00:00'),
        ]);

        Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Past Event',
            'status' => EventStatus::Completed,
            'start_datetime' => Carbon::parse('2020-01-01 18:00:00'),
        ]);

        TicketType::factory()->create([
            'venue_id' => $venue->id,
            'event_id' => $upcoming->id,
            'quantity' => 100,
            'quantity_sold' => 25,
        ]);

        $dashboard = app(OrganizerDashboardService::class)->build($venue->id);

        $this->assertCount(1, $dashboard->events);
        $this->assertSame($upcoming->id, $dashboard->events[0]['id']);
        $this->assertSame(100, $dashboard->events[0]['capacity']);
        $this->assertSame(25, $dashboard->events[0]['tickets_sold']);
        $this->assertSame(75, $dashboard->events[0]['remaining']);
        $this->assertSame(75, $dashboard->kpis['tickets_remaining']);
    }
}
