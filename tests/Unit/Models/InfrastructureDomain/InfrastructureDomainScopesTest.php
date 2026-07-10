<?php

namespace Tests\Unit\Models\InfrastructureDomain;

use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Models\EmailTemplate;
use App\Models\OutboxEvent;
use App\Models\SmsTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InfrastructureDomainScopesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function outbox_event_scopes_filter_pending_failed_and_processed(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        OutboxEvent::factory()->forVenue($venue)->create(['status' => OutboxEventStatus::Pending]);
        $failed = OutboxEvent::factory()->forVenue($venue)->failed()->create();
        $processed = OutboxEvent::factory()->forVenue($venue)->processed()->create();

        $this->assertCount(1, OutboxEvent::query()->pending()->get());
        $this->assertCount(1, OutboxEvent::query()->failed()->get());
        $this->assertTrue(OutboxEvent::query()->failed()->first()->is($failed));

        $this->assertCount(1, OutboxEvent::query()->processed()->get());
        $this->assertTrue(OutboxEvent::query()->processed()->first()->is($processed));

        $this->assertCount(1, OutboxEvent::query()->withStatus(OutboxEventStatus::Pending)->get());
    }

    #[Test]
    public function template_active_scope_filters_inactive_records(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        EmailTemplate::factory()->forVenue($venue)->inactive()->create(['slug' => 'inactive-email']);
        $activeEmail = EmailTemplate::factory()->forVenue($venue)->create(['slug' => 'active-email']);

        SmsTemplate::factory()->forVenue($venue)->inactive()->create(['slug' => 'inactive-sms']);
        $activeSms = SmsTemplate::factory()->forVenue($venue)->create(['slug' => 'active-sms']);

        $this->assertCount(1, EmailTemplate::query()->active()->get());
        $this->assertTrue(EmailTemplate::query()->active()->first()->is($activeEmail));

        $this->assertCount(1, SmsTemplate::query()->active()->get());
        $this->assertTrue(SmsTemplate::query()->active()->first()->is($activeSms));
    }
}
