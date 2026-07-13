<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommissionPaymentArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $allowedCommissionPaymentWritePaths = [
        'app/Services/Commissions/CommissionPaymentService.php',
    ];

    #[Test]
    public function only_commission_payment_service_creates_commission_payment_rows(): void
    {
        $violations = $this->findModelCreateViolations('CommissionPayment', $this->allowedCommissionPaymentWritePaths);

        $this->assertSame([], $violations, 'Commission payments must only be created via CommissionPaymentService');
    }

    #[Test]
    public function commission_payment_service_does_not_update_or_delete_rows(): void
    {
        $source = file_get_contents(base_path('app/Services/Commissions/CommissionPaymentService.php'));
        $this->assertIsString($source);

        foreach (['->update(', '->delete(', '::destroy(', 'forceDelete('] as $forbidden) {
            $this->assertStringNotContainsString(
                $forbidden,
                $source,
                "CommissionPaymentService must not mutate existing commission payment rows ({$forbidden})",
            );
        }
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
