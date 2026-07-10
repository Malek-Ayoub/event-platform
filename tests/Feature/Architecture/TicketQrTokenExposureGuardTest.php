<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketQrTokenExposureGuardTest extends TestCase
{
    #[Test]
    public function http_resources_never_expose_qr_token(): void
    {
        $violations = [];

        foreach ($this->phpFilesIn(base_path('app/Http/Resources')) as $path) {
            $source = file_get_contents($path);
            $this->assertIsString($source);

            if (str_contains($source, 'qr_token')) {
                $violations[] = str_replace(base_path().'/', '', str_replace('\\', '/', $path));
            }
        }

        $this->assertSame([], $violations, 'Public API resources must not expose qr_token');
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
