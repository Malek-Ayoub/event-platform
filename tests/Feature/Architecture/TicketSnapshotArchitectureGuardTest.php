<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketSnapshotArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $allowedSnapshotWritePaths = [
        'app/Services/Tickets/Snapshots/TicketSnapshotService.php',
    ];

    /** @var list<string> */
    private array $allowedArtifactWritePaths = [
        'app/Services/Tickets/Artifacts/TicketArtifactService.php',
    ];

    #[Test]
    public function only_ticket_snapshot_service_creates_snapshot_rows(): void
    {
        $violations = $this->findModelCreateViolations('TicketSnapshot', $this->allowedSnapshotWritePaths);

        $this->assertSame([], $violations, 'Ticket snapshots must only be created via TicketSnapshotService');
    }

    #[Test]
    public function only_ticket_artifact_service_creates_artifact_rows(): void
    {
        $violations = $this->findModelCreateViolations('TicketArtifact', $this->allowedArtifactWritePaths);

        $this->assertSame([], $violations, 'Ticket artifacts must only be created via TicketArtifactService');
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
