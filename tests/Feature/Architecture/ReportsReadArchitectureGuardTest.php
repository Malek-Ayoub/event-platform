<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ReportsReadArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $readServices = [
        'app/Services/Reports/OrganizerReportService.php',
        'app/Services/Reports/AdminReportService.php',
        'app/Services/Reports/Queries/PlatformRevenueQuery.php',
        'app/Services/Reports/Queries/CommissionReportQuery.php',
        'app/Services/Reports/Queries/TopVenuesQuery.php',
        'app/Services/Reports/Queries/TopEventsQuery.php',
        'app/Services/Reports/Queries/PaymentMethodReportQuery.php',
        'app/Services/Reports/Queries/RefundReportQuery.php',
    ];

    #[Test]
    public function report_read_services_do_not_create_financial_records(): void
    {
        foreach ($this->readServices as $relativePath) {
            $source = file_get_contents(base_path($relativePath));
            $this->assertIsString($source);

            foreach ([
                '::query()->create',
                '->update(',
                '->delete(',
                'OutboxService',
            ] as $forbidden) {
                $this->assertStringNotContainsString(
                    $forbidden,
                    $source,
                    "{$relativePath} must remain read-only ({$forbidden})",
                );
            }
        }
    }
}
