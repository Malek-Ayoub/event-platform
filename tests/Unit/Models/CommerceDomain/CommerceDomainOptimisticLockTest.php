<?php

namespace Tests\Unit\Models\CommerceDomain;

use App\Exceptions\StaleModelException;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommerceDomainOptimisticLockTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function tax_rate_update_with_version_increments_version(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $taxRate = TaxRate::factory()->create(['venue_id' => $venue->id, 'version' => 1]);

        $taxRate->updateWithVersion(['name' => 'Updated VAT'], 1);

        $this->assertSame('Updated VAT', $taxRate->fresh()->name);
        $this->assertSame(2, $taxRate->fresh()->version);
    }

    #[Test]
    public function tax_rate_update_with_version_throws_on_conflict(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $taxRate = TaxRate::factory()->create(['venue_id' => $venue->id, 'version' => 1]);

        TaxRate::query()->whereKey($taxRate->id)->update(['version' => 2]);

        $this->expectException(StaleModelException::class);

        $taxRate->updateWithVersion(['name' => 'Conflict'], 1);
    }
}
