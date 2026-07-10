<?php

namespace Tests\Unit\Services\Notifications\Templates;

use App\Exceptions\Notifications\NotificationTemplateNotFoundException;
use App\Models\EmailTemplate;
use App\Services\Notifications\Templates\EmailTemplateRenderer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailTemplateRendererTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_renders_configured_platform_templates(): void
    {
        $rendered = app(EmailTemplateRenderer::class)->render('order.paid', [
            'customer_name' => 'Alex',
            'event_name' => 'Summer Fest',
            'order_number' => 'ORD-100',
            'ticket_count' => '2',
            'total' => '120.00',
            'ticket_download_url' => 'https://example.com/tickets/ORD-100',
        ]);

        $this->assertSame('Your tickets for Summer Fest', $rendered->subject);
        $this->assertStringContainsString('Hello Alex', $rendered->body);
        $this->assertStringContainsString('ORD-100', $rendered->body);
        $this->assertStringContainsString('https://example.com/tickets/ORD-100', $rendered->body);
    }

    #[Test]
    public function it_prefers_venue_specific_templates_over_platform_defaults(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        EmailTemplate::factory()->forVenue($venue)->create([
            'slug' => 'order.paid',
            'subject' => 'Venue {{event_name}}',
            'body' => 'Hi {{customer_name}} from venue {{order_number}}',
        ]);

        $rendered = app(EmailTemplateRenderer::class)->render('order.paid', [
            'customer_name' => 'Sam',
            'event_name' => 'Local Show',
            'order_number' => 'ORD-200',
        ], $venue->id);

        $this->assertSame('Venue Local Show', $rendered->subject);
        $this->assertSame('Hi Sam from venue ORD-200', $rendered->body);
    }

    #[Test]
    public function it_throws_when_no_template_can_be_resolved(): void
    {
        $this->expectException(NotificationTemplateNotFoundException::class);

        app(EmailTemplateRenderer::class)->render('missing.template', []);
    }
}
