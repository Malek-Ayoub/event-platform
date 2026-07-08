<?php

namespace Tests\Unit\Services\Events;

use App\Enums\EventDomain\EventStatus;
use App\Exceptions\Events\InvalidEventStateTransitionException;
use App\Models\ActivityLog;
use App\Models\Event;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use App\Services\Events\Data\CreateEventData;
use App\Services\Events\Data\UpdateEventData;
use App\Services\Events\EventService;
use App\Services\OutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class EventServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_event_with_activity_log_and_outbox(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = app(EventService::class)->createEvent(new CreateEventData(
            name: 'Summer Festival',
            slug: null,
            categoryId: null,
            description: 'A great event',
            bannerUrl: null,
            gallery: null,
            videoUrl: null,
            djInfo: null,
            startDatetime: now()->addWeek(),
            endDatetime: now()->addWeeks(2),
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame(EventStatus::Draft, $event->status);
        $this->assertSame('summer-festival', $event->slug);

        $this->assertDatabaseHas('activity_logs', [
            'venue_id' => $venue->id,
            'entity_type' => Event::class,
            'entity_id' => $event->id,
            'action' => 'created',
        ]);

        $outbox = OutboxEvent::query()->where('event_type', 'event.created')->first();
        $this->assertNotNull($outbox);
        $this->assertArrayHasKey('occurred_at', $outbox->payload);
    }

    #[Test]
    public function it_rolls_back_when_outbox_fails_on_create(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $this->mock(OutboxService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('outbox failed'));
        });

        $exception = null;

        try {
            app(EventService::class)->createEvent(new CreateEventData(
                name: 'Rollback Event',
                slug: null,
                categoryId: null,
                description: null,
                bannerUrl: null,
                gallery: null,
                videoUrl: null,
                djInfo: null,
                startDatetime: now()->addWeek(),
                endDatetime: now()->addWeeks(2),
                actor: $owner,
                ipAddress: '127.0.0.1',
            ));
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertSame('outbox failed', $exception->getMessage());
        $this->assertDatabaseCount('events', 0);
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }

    #[Test]
    public function publish_event_is_idempotent_when_already_published(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->published()->create(['venue_id' => $venue->id, 'version' => 2]);

        $result = app(EventService::class)->publishEvent($event);

        $this->assertSame(EventStatus::Published, $result->status);
        $this->assertSame(2, $result->version);
        $this->assertSame(0, OutboxEvent::query()->where('event_type', 'event.published')->count());
    }

    #[Test]
    public function it_publishes_draft_event(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id, 'version' => 1]);

        $published = app(EventService::class)->publishEvent($event, $owner, '127.0.0.1');

        $this->assertSame(EventStatus::Published, $published->status);
        $this->assertSame(2, $published->version);
        $this->assertDatabaseHas('activity_logs', ['action' => 'published', 'entity_id' => $event->id]);
    }

    #[Test]
    public function archive_event_rejects_unsupported_status(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id, 'status' => EventStatus::Draft]);

        $this->expectException(InvalidEventStateTransitionException::class);

        app(EventService::class)->archiveEvent($event);
    }

    #[Test]
    public function it_archives_published_event_to_completed(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->published()->create(['venue_id' => $venue->id, 'version' => 3]);

        $archived = app(EventService::class)->archiveEvent($event);

        $this->assertSame(EventStatus::Completed, $archived->status);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'event.archived', 'aggregate_id' => $event->id]);
    }

    #[Test]
    public function it_updates_event_with_optimistic_lock(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id, 'name' => 'Old Name', 'version' => 1]);

        $updated = app(EventService::class)->updateEvent($event, new UpdateEventData(
            expectedVersion: 1,
            name: 'New Name',
            actor: $owner,
        ));

        $this->assertSame('New Name', $updated->name);
        $this->assertSame(2, $updated->version);
    }
}
