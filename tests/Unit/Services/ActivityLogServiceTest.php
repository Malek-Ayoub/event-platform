<?php

namespace Tests\Unit\Services;

use App\Models\ActivityLog;
use App\Models\Event;
use App\Services\ActivityLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityLogServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_appends_activity_log_for_entity(): void
    {
        ['venue' => $venue, 'user' => $actor] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $service = app(ActivityLogService::class);

        $log = $service->record(
            actor: $actor,
            entity: $event,
            action: 'updated',
            oldValues: ['status' => 'draft'],
            newValues: ['status' => 'published'],
            changedFields: ['status'],
            ipAddress: '127.0.0.1',
        );

        $this->assertInstanceOf(ActivityLog::class, $log);
        $this->assertSame($venue->id, $log->venue_id);
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertSame(Event::class, $log->entity_type);
        $this->assertSame($event->id, $log->entity_id);
        $this->assertSame('updated', $log->action);
        $this->assertSame(['status' => 'draft'], $log->old_values);
        $this->assertSame(['status' => 'published'], $log->new_values);
        $this->assertSame(['status'], $log->changed_fields);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertNull($log->updated_at);
    }

    #[Test]
    public function activity_log_is_scoped_to_current_tenant(): void
    {
        ['venue' => $venueA, 'user' => $actor] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();

        $this->bindTenant($venueA->id);
        $eventA = Event::factory()->create(['venue_id' => $venueA->id]);
        app(ActivityLogService::class)->record($actor, $eventA, 'created');

        $this->bindTenant($venueB->id);
        $this->assertCount(0, ActivityLog::query()->get());

        $this->bindTenant($venueA->id);
        $this->assertCount(1, ActivityLog::query()->get());
    }
}
