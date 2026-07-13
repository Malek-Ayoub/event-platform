<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class AdminDashboardReadArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $forbiddenWriteDependencies = [
        'PaymentService',
        'OrderService',
        'SettlementEntryService',
        'CommissionPaymentService',
        'OutboxService',
    ];

    /** @var list<string> */
    private array $forbiddenWritePatterns = [
        '::query()->create',
        '->create(',
        '->update(',
        '->delete(',
        'OutboxService',
    ];

    /** @var list<string> */
    private array $adminDashboardControllers = [
        'app/Http/Controllers/Api/AdminDashboardController.php',
    ];

    #[Test]
    public function admin_dashboard_services_remain_read_only(): void
    {
        foreach ($this->adminDashboardServiceFiles() as $relativePath) {
            $source = $this->sourceOf($relativePath);

            foreach ($this->forbiddenWritePatterns as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source,
                    "{$relativePath} must remain read-only ({$forbidden})",
                );
            }
        }
    }

    #[Test]
    public function admin_dashboard_layer_does_not_depend_on_write_domain_services(): void
    {
        $files = array_merge($this->adminDashboardServiceFiles(), $this->adminDashboardControllers);

        foreach ($files as $relativePath) {
            $source = $this->sourceOf($relativePath);

            foreach ($this->forbiddenWriteDependencies as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source,
                    "{$relativePath} must not depend on write-side service {$forbidden}",
                );
            }
        }
    }

    #[Test]
    public function admin_dashboard_controller_delegates_to_admin_dashboard_service_only(): void
    {
        foreach ($this->adminDashboardControllers as $relativePath) {
            $source = $this->sourceOf($relativePath);

            $this->assertStringContainsString('AdminDashboardService', $source);
            $this->assertStringNotContainsString('::query()', $source);
            $this->assertStringNotContainsString('DB::', $source);
        }
    }

    /**
     * @return list<string>
     */
    private function adminDashboardServiceFiles(): array
    {
        $files = [
            'app/Services/Dashboard/AdminDashboardService.php',
        ];

        $base = base_path('app/Services/Dashboard/Queries/Admin');
        if (! is_dir($base)) {
            return $files;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, RecursiveDirectoryIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1));
            }
        }

        sort($files);

        return $files;
    }

    private function sourceOf(string $relativePath): string
    {
        $source = file_get_contents(base_path($relativePath));
        $this->assertIsString($source);

        return $source;
    }
}
