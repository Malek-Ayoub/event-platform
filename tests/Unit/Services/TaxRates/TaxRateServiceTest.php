<?php

namespace Tests\Unit\Services\TaxRates;

use App\Models\ActivityLog;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\TaxRate;
use App\Services\OutboxService;
use App\Services\TaxRates\Data\CreateTaxRateData;
use App\Services\TaxRates\TaxRateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TaxRateServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_tax_rate_with_activity_log_and_outbox(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $taxRate = app(TaxRateService::class)->createTaxRate(new CreateTaxRateData(
            name: 'VAT',
            rate: '0.1500',
            isActive: true,
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame('VAT', $taxRate->name);

        $this->assertDatabaseHas('activity_logs', [
            'venue_id' => $venue->id,
            'entity_type' => TaxRate::class,
            'entity_id' => $taxRate->id,
            'action' => 'created',
        ]);

        $outbox = OutboxEvent::query()->where('event_type', 'tax_rate.created')->first();
        $this->assertNotNull($outbox);
        $this->assertArrayHasKey('occurred_at', $outbox->payload);
    }

    #[Test]
    public function it_rolls_back_when_outbox_fails_on_create(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $this->mock(OutboxService::class, function ($mock): void {
            $mock->shouldReceive('record')->once()->andThrow(new RuntimeException('outbox failed'));
        });

        $exception = null;

        try {
            app(TaxRateService::class)->createTaxRate(new CreateTaxRateData(
                name: 'Rollback Tax',
                rate: '0.1000',
                isActive: true,
                actor: $owner,
                ipAddress: '127.0.0.1',
            ));
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertDatabaseCount('tax_rates', 0);
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }
}
