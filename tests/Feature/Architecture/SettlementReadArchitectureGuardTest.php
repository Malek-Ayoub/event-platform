<?php

namespace Tests\Feature\Architecture;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SettlementReadArchitectureGuardTest extends TestCase
{
    /** @var list<string> */
    private array $readServices = [
        'app/Services/Settlements/SettlementSummaryService.php',
        'app/Services/Settlements/SettlementLedgerService.php',
        'app/Services/Settlements/AdminSettlementVenueListService.php',
        'app/Services/Settlements/SettlementMetaService.php',
    ];

    #[Test]
    public function settlement_read_services_do_not_create_financial_records(): void
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
