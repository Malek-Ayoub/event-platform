<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketCheckInArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $allowedCheckInWritePaths = [
        'app/Services/Tickets/CheckIn/TicketCheckInService.php',
    ];

    #[Test]
    public function only_ticket_check_in_service_creates_check_in_rows(): void
    {
        $violations = $this->findModelCreateViolations('TicketCheckIn', $this->allowedCheckInWritePaths);

        $this->assertSame([], $violations, 'Ticket check-ins must only be created via TicketCheckInService');
    }

    #[Test]
    public function tenant_routes_do_not_expose_ticket_id_check_in_endpoints(): void
    {
        $source = file_get_contents(base_path('routes/tenant.php'));
        $this->assertIsString($source);

        $this->assertStringNotContainsString('tickets/{ticket}', $source);
        $this->assertStringNotContainsString('tickets/{id}', $source);
        $this->assertStringContainsString("post('tickets/check-in'", $source);
    }

    #[Test]
    public function ticket_check_in_service_resolves_tickets_only_by_qr_token(): void
    {
        $source = file_get_contents(base_path('app/Services/Tickets/CheckIn/TicketCheckInService.php'));
        $this->assertIsString($source);

        $this->assertStringContainsString("where('qr_token'", $source);
        $this->assertStringNotContainsString('whereKey(', $source);
        $this->assertStringNotContainsString('findOrFail($', $source);
    }

    /**
     * @param  list<string>  $allowedRelativePaths
     * @return list<string>
     */
    private function findModelCreateViolations(string $model, array $allowedRelativePaths): array
    {
        $violations = [];
        $needle = "{$model}::query()->create";

        foreach ($this->phpFilesIn(base_path('app')) as $path) {
            $relative = str_replace(str_replace('\\', '/', base_path()).'/', '', str_replace('\\', '/', $path));

            if (in_array($relative, $allowedRelativePaths, true)) {
                continue;
            }

            $source = file_get_contents($path);
            $this->assertIsString($source);

            if (str_contains($source, $needle)) {
                $violations[] = $relative;
            }
        }

        return $violations;
    }

    /**
     * @return list<string>
     */
    private function phpFilesIn(string $directory): array
    {
        if (! is_dir($directory)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
