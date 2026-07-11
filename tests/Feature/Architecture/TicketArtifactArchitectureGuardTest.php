<?php

namespace Tests\Feature\Architecture;

use App\Services\Orders\TicketService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketArtifactArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $forbiddenTicketServiceSymbols = [
        'QrImageGenerator',
        'TicketQrService',
        'EndroidQrCode',
        'Endroid\\QrCode',
    ];

    #[Test]
    public function frozen_ticket_service_does_not_generate_qr_artifacts(): void
    {
        $source = file_get_contents(app_path('Services/Orders/TicketService.php'));
        $this->assertIsString($source);

        foreach ($this->forbiddenTicketServiceSymbols as $symbol) {
            $this->assertStringNotContainsString(
                $symbol,
                $source,
                'TicketService must not generate QR artifacts — use TicketQrService via outbox consumer',
            );
        }
    }

    #[Test]
    public function ticket_qr_service_is_the_qr_artifact_entry_point(): void
    {
        $this->assertTrue(class_exists(TicketService::class));
        $this->assertTrue(class_exists(\App\Services\Tickets\TicketQrService::class));
    }
}
