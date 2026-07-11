<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketRendererArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $forbiddenLiveModelReferences = [
        'App\\Models\\Event',
        'App\\Models\\Venue',
        'App\\Models\\TicketType',
        'App\\Models\\Order',
        'App\\Models\\PaymentTransaction',
        'App\\Models\\PaymentAccount',
        'Event::query',
        'Venue::query',
        'TicketType::query',
        'Order::query',
        'PaymentTransaction::query',
        'PaymentAccount::query',
    ];

    #[Test]
    public function ticket_renderers_must_not_read_live_event_venue_or_ticket_type_models(): void
    {
        $violations = [];

        foreach ($this->rendererFiles() as $path) {
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
    private function rendererFiles(): array
    {
        $files = [];
        $directories = [
            base_path('app/Services/Tickets/Renderers'),
        ];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.php')) {
                    $files[] = $file->getPathname();
                }
            }
        }

        foreach ($this->phpFilesIn(base_path('app/Services/Tickets')) as $path) {
            if (str_contains(basename($path), 'Renderer') && ! in_array($path, $files, true)) {
                $files[] = $path;
            }
        }

        sort($files);

        return $files;
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

        return $files;
    }
}
