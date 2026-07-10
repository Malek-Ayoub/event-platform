<?php

namespace Tests\Unit\Models\InfrastructureDomain;

use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Media;
use App\Models\Notification;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PlatformSetting;
use App\Models\SmsTemplate;
use App\Models\User;
use App\Models\Venue;
use App\Support\Concerns\BelongsToVenue;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfrastructureDomainRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function venue_has_many_infrastructure_resources(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $user = User::factory()->create();

        $notification = Notification::factory()->forVenue($venue)->forUser($user)->create();
        $emailTemplate = EmailTemplate::factory()->forVenue($venue)->create();
        $smsTemplate = SmsTemplate::factory()->forVenue($venue)->create();
        $activityLog = ActivityLog::factory()->forVenue($venue)->forEntity($event)->create();
        $outboxEvent = OutboxEvent::factory()->forVenue($venue)->create();
        $media = Media::factory()->forMediable($event)->forVenue($venue)->create();
        $document = Document::factory()->forDocumentable($event)->forVenue($venue)->create();

        $venue = $venue->fresh();

        $this->assertInstanceOf(HasMany::class, (new Venue)->notifications());
        $this->assertTrue($venue->notifications->contains($notification));
        $this->assertTrue($venue->emailTemplates->contains($emailTemplate));
        $this->assertTrue($venue->smsTemplates->contains($smsTemplate));
        $this->assertTrue($venue->activityLogs->contains($activityLog));
        $this->assertTrue($venue->outboxEvents->contains($outboxEvent));
        $this->assertTrue($venue->media->contains($media));
        $this->assertTrue($venue->documents->contains($document));
    }

    #[Test]
    public function user_has_many_notifications_and_activity_logs_as_actor(): void
    {
        ['venue' => $venue, 'user' => $actor] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $notification = Notification::factory()->forVenue($venue)->forUser($actor)->create();
        $activityLog = ActivityLog::factory()->forVenue($venue)->forActor($actor)->forEntity($event)->create();

        $this->assertTrue($actor->fresh()->notifications->contains($notification));
        $this->assertTrue($actor->fresh()->activityLogs->contains($activityLog));
    }

    #[Test]
    public function notification_belongs_to_venue_and_user(): void
    {
        ['venue' => $venue, 'user' => $user] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $notification = Notification::factory()->forVenue($venue)->forUser($user)->create();

        $this->assertInstanceOf(BelongsTo::class, (new Notification)->venue());
        $this->assertInstanceOf(BelongsTo::class, (new Notification)->user());
        $this->assertTrue($notification->venue->is($venue));
        $this->assertTrue($notification->user->is($user));
    }

    #[Test]
    public function activity_log_belongs_to_venue_actor_and_entity(): void
    {
        ['venue' => $venue, 'user' => $actor] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $log = ActivityLog::factory()->forVenue($venue)->forActor($actor)->forEntity($event)->create();

        $this->assertInstanceOf(BelongsTo::class, (new ActivityLog)->venue());
        $this->assertInstanceOf(BelongsTo::class, (new ActivityLog)->actor());
        $this->assertInstanceOf(MorphTo::class, (new ActivityLog)->entity());
        $this->assertTrue($log->venue->is($venue));
        $this->assertTrue($log->actor->is($actor));
        $this->assertTrue($log->entity->is($event));
    }

    #[Test]
    public function outbox_event_belongs_to_venue_and_aggregate(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $outboxEvent = OutboxEvent::factory()->forVenue($venue)->forAggregate($order)->create();

        $this->assertInstanceOf(BelongsTo::class, (new OutboxEvent)->venue());
        $this->assertInstanceOf(MorphTo::class, (new OutboxEvent)->aggregate());
        $this->assertTrue($outboxEvent->venue->is($venue));
        $this->assertTrue($outboxEvent->aggregate->is($order));
    }

    #[Test]
    public function media_and_document_use_polymorphic_relations(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $media = Media::factory()->forMediable($event)->forVenue($venue)->create();
        $document = Document::factory()->forDocumentable($event)->forVenue($venue)->create();

        $this->assertInstanceOf(MorphTo::class, (new Media)->mediable());
        $this->assertInstanceOf(MorphTo::class, (new Document)->documentable());
        $this->assertTrue($media->mediable->is($event));
        $this->assertTrue($document->documentable->is($event));
    }

    #[Test]
    public function tenant_scoped_infrastructure_models_are_isolated_by_venue(): void
    {
        ['venue' => $venueA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();

        $this->bindTenant($venueA->id);
        EmailTemplate::factory()->forVenue($venueA)->create(['slug' => 'welcome']);

        $this->bindTenant($venueB->id);
        EmailTemplate::factory()->forVenue($venueB)->create(['slug' => 'welcome']);

        $this->bindTenant($venueA->id);
        $this->assertCount(1, EmailTemplate::query()->get());

        $this->bindTenant($venueB->id);
        $this->assertCount(1, EmailTemplate::query()->get());
    }

    #[Test]
    public function platform_setting_is_not_tenant_scoped(): void
    {
        $this->assertFalse(in_array(BelongsToVenue::class, class_uses_recursive(PlatformSetting::class), true));
    }
}
