<?php

namespace Tests\Feature\Architecture;

use App\Services\Orders\TicketService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketCreationArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $allowedTicketCreationPaths = [
        'app/Services/Orders/TicketService.php',
    ];

    #[Test]
    public function only_ticket_service_creates_ticket_rows_directly(): void
    {
        $violations = [];

        foreach ($this->phpFilesIn(base_path('app')) as $path) {
            $normalized = str_replace('\\', '/', $path);
            $relative = str_replace(str_replace('\\', '/', base_path()).'/', '', $normalized);

            if (in_array($relative, $this->allowedTicketCreationPaths, true)) {
                continue;
            }

            $source = file_get_contents($path);
            $this->assertIsString($source);

            if (str_contains($source, 'Ticket::query()->create')) {
                $violations[] = $relative;
            }
        }

        $this->assertSame([], $violations, 'Ticket rows must only be created via TicketService');
    }

    #[Test]
    public function ticket_service_is_the_single_creation_entry_point(): void
    {
        $this->assertFileExists(app_path('Services/Orders/TicketService.php'));
        $this->assertTrue(class_exists(TicketService::class));
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
