<?php

namespace Tests\Unit\Models\InfrastructureDomain;

use App\Enums\InfrastructureDomain\MediaType;
use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Enums\InfrastructureDomain\WebhookLogStatus;
use App\Models\ActivityLog;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\Media;
use App\Models\Notification;
use App\Models\OutboxEvent;
use App\Models\PlatformSetting;
use App\Models\WebhookLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfrastructureDomainCastsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function platform_setting_casts_commission_rate_settings_and_version(): void
    {
        $setting = PlatformSetting::factory()->create([
            'commission_rate' => '2.50',
            'settings' => ['default_currency' => 'EUR'],
            'version' => 3,
        ]);

        $this->assertSame('2.50', $setting->commission_rate);
        $this->assertSame(['default_currency' => 'EUR'], $setting->settings);
        $this->assertSame(3, $setting->version);
    }

    #[Test]
    public function notification_casts_data_and_read_at(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $readAt = now()->startOfSecond();
        $notification = Notification::factory()->forVenue($venue)->create([
            'data' => ['order_id' => 42],
            'read_at' => $readAt,
        ]);

        $this->assertSame(['order_id' => 42], $notification->data);
        $this->assertTrue($notification->read_at->equalTo($readAt));
        $this->assertIsString($notification->id);
    }

    #[Test]
    public function email_template_casts_variables_and_is_active(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $template = EmailTemplate::factory()->forVenue($venue)->create([
            'variables' => ['name'],
            'is_active' => false,
        ]);

        $this->assertSame(['name'], $template->variables);
        $this->assertFalse($template->is_active);
    }

    #[Test]
    public function webhook_log_casts_status_enum(): void
    {
        $log = WebhookLog::factory()->failed()->create();

        $this->assertSame(WebhookLogStatus::Failed, $log->status);
    }

    #[Test]
    public function activity_log_casts_json_columns(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $log = ActivityLog::factory()->forVenue($venue)->create([
            'old_values' => ['price' => '10.00'],
            'new_values' => ['price' => '12.00'],
            'changed_fields' => ['price'],
        ]);

        $this->assertSame(['price' => '10.00'], $log->old_values);
        $this->assertSame(['price' => '12.00'], $log->new_values);
        $this->assertSame(['price'], $log->changed_fields);
    }

    #[Test]
    public function outbox_event_casts_status_payload_and_processed_at(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $processedAt = now()->startOfSecond();
        $event = OutboxEvent::factory()->forVenue($venue)->create([
            'status' => OutboxEventStatus::Sent,
            'payload' => ['ticket_id' => 7],
            'processed_at' => $processedAt,
        ]);

        $this->assertSame(OutboxEventStatus::Sent, $event->status);
        $this->assertSame(['ticket_id' => 7], $event->payload);
        $this->assertTrue($event->processed_at->equalTo($processedAt));
    }

    #[Test]
    public function media_casts_type_enum(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $media = Media::factory()->forMediable($event)->forVenue($venue)->video()->create();

        $this->assertSame(MediaType::Video, $media->type);
    }
}
