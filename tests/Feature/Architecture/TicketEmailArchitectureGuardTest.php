<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketEmailArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $forbiddenLiveModelReferences = [
        'App\\Models\\Event',
        'App\\Models\\Venue',
        'App\\Models\\TicketType',
        'App\\Models\\Order',
        'App\\Models\\PaymentTransaction',
        'App\\Models\\PaymentAccount',
    ];

    #[Test]
    public function ticket_email_rendering_must_not_read_live_domain_models(): void
    {
        $violations = [];

        foreach ($this->emailRenderingFiles() as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);
            $relative = str_replace(str_replace('\\', '/', base_path()).'/', '', str_replace('\\', '/', $path));

            foreach ($this->forbiddenLiveModelReferences as $forbidden) {
                if (str_contains($source, $forbidden)) {
                    $violations[] = "{$relative} references {$forbidden}";
                }
            }
        }

        $this->assertSame([], $violations);
    }

    /**
     * @return list<string>
     */
    private function emailRenderingFiles(): array
    {
        return [
            base_path('app/Mail/TicketIssuedMail.php'),
            base_path('app/Services/Tickets/TicketEmailService.php'),
            base_path('resources/views/emails/ticket-issued.blade.php'),
        ];
    }
}
