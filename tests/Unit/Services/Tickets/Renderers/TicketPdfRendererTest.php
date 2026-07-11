<?php

namespace Tests\Unit\Services\Tickets\Renderers;

use App\Services\Tickets\Renderers\TicketPdfRenderer;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketPdfRendererTest extends TestCase
{
    #[Test]
    public function it_renders_a_pdf_from_snapshot_payload_and_qr_image_only(): void
    {
        $renderer = new TicketPdfRenderer;

        $payload = [
            'event' => ['name' => 'Snapshot Event', 'starts_at' => '2026-08-01T20:00:00+00:00', 'ends_at' => null],
            'venue' => ['name' => 'Snapshot Venue'],
            'ticket_type' => ['name' => 'GA', 'color' => '#111827'],
            'holder' => ['name' => 'Snapshot Holder'],
            'seat' => ['label' => 'A1'],
            'price' => ['amount' => '50.00', 'currency' => 'USD'],
            'ticket' => ['number' => 'EV000001-000001', 'issued_at' => '2026-08-01T18:00:00+00:00'],
        ];

        $qrPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        ) ?: '';

        $pdf = $renderer->render($payload, $qrPng);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertNotSame('', $pdf);
        $this->assertStringNotContainsString('http://', $pdf);
        $this->assertStringNotContainsString('https://', $pdf);
    }

    #[Test]
    public function it_embeds_the_qr_image_as_inline_png_bytes(): void
    {
        $renderer = new TicketPdfRenderer;
        $reflection = new \ReflectionClass($renderer);
        $method = $reflection->getMethod('buildHtml');
        $method->setAccessible(true);

        $payload = [
            'event' => ['name' => 'Event', 'starts_at' => null, 'ends_at' => null],
            'venue' => ['name' => 'Venue'],
            'ticket_type' => ['name' => 'GA', 'color' => null],
            'holder' => ['name' => 'Holder'],
            'seat' => ['label' => null],
            'price' => ['amount' => '10.00', 'currency' => 'USD'],
            'ticket' => ['number' => 'T-1', 'issued_at' => null],
        ];

        $qrPng = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        ) ?: '';

        $html = $method->invoke($renderer, $payload, base64_encode($qrPng));

        $this->assertStringContainsString('data:image/png;base64,', $html);
        $this->assertStringNotContainsString('tickets/qr/', $html);
        $this->assertStringNotContainsString('http://', $html);
    }
}
