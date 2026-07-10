<?php

namespace Tests\Unit\Repositories;

use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use App\Repositories\OutboxRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OutboxRepositoryTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_claims_pending_events_and_marks_them_processing(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $first = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.created',
            'status' => OutboxEventStatus::Pending,
        ]);
        $second = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.created',
            'status' => OutboxEventStatus::Pending,
        ]);

        $claimed = app(OutboxRepository::class)->claimPendingBatch(10);

        $this->assertCount(2, $claimed);
        $this->assertSame(OutboxEventStatus::Processing, $first->fresh()->status);
        $this->assertSame(OutboxEventStatus::Processing, $second->fresh()->status);
    }

    #[Test]
    public function it_does_not_reclaim_events_already_processing(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.created',
            'status' => OutboxEventStatus::Processing,
        ]);

        $claimed = app(OutboxRepository::class)->claimPendingBatch(10);

        $this->assertCount(0, $claimed);
    }

    #[Test]
    public function it_releases_stale_processing_events_back_to_pending(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $stale = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.created',
            'status' => OutboxEventStatus::Processing,
            'updated_at' => now()->subHour(),
        ]);

        app(OutboxRepository::class)->releaseStaleProcessing();

        $this->assertSame(OutboxEventStatus::Pending, $stale->fresh()->status);
    }

    #[Test]
    public function it_respects_retry_backoff_before_reclaiming_failed_attempts(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.created',
            'status' => OutboxEventStatus::Pending,
            'attempts' => 1,
            'updated_at' => now(),
        ]);

        $claimed = app(OutboxRepository::class)->claimPendingBatch(10);

        $this->assertCount(0, $claimed);
    }

    #[Test]
    public function it_reclaims_events_after_retry_backoff_elapsed(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = OutboxEvent::factory()->forVenue($venue)->create([
            'event_type' => 'order.created',
            'status' => OutboxEventStatus::Pending,
            'attempts' => 1,
            'updated_at' => now()->subMinutes(2),
        ]);

        $claimed = app(OutboxRepository::class)->claimPendingBatch(10);

        $this->assertCount(1, $claimed);
        $this->assertSame($event->id, $claimed->first()->id);
    }

    #[Test]
    public function it_claims_without_tenant_scope(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();

        $this->bindTenant($venueB->id);

        $foreign = OutboxEvent::factory()->forVenue($venueB)->create([
            'event_type' => 'order.created',
            'status' => OutboxEventStatus::Pending,
        ]);

        $this->bindTenant($venueA->id);

        $claimed = app(OutboxRepository::class)->claimPendingBatch(10);

        $this->assertTrue(
            $claimed->contains(fn (OutboxEvent $event): bool => $event->id === $foreign->id),
        );

        $this->assertSame(
            0,
            OutboxEvent::query()->withoutGlobalScope(BelongsToVenueScope::class)->pending()->count(),
        );
    }
}
