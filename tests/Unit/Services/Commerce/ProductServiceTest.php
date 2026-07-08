<?php

namespace Tests\Unit\Services\Commerce;

use App\Models\ActivityLog;
use App\Models\OutboxEvent;
use App\Models\Product;
use App\Models\Scopes\BelongsToVenueScope;
use App\Services\Commerce\Data\CreateProductData;
use App\Services\Commerce\ProductService;
use App\Services\OutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class ProductServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_creates_product_with_activity_log_and_outbox(): void
    {
        ['venue' => $venue, 'user' => $owner] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $product = app(ProductService::class)->createProduct(new CreateProductData(
            name: 'Merch T-Shirt',
            description: 'Official event shirt',
            price: '29.99',
            eventId: null,
            isActive: true,
            actor: $owner,
            ipAddress: '127.0.0.1',
        ));

        $this->assertSame('Merch T-Shirt', $product->name);
        $this->assertSame('29.99', $product->price);

        $this->assertDatabaseHas('activity_logs', [
            'venue_id' => $venue->id,
            'entity_type' => Product::class,
            'entity_id' => $product->id,
            'action' => 'created',
        ]);

        $outbox = OutboxEvent::query()->where('event_type', 'product.created')->first();
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
            app(ProductService::class)->createProduct(new CreateProductData(
                name: 'Rollback Product',
                description: null,
                price: '10.00',
                eventId: null,
                isActive: true,
                actor: $owner,
                ipAddress: '127.0.0.1',
            ));
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertNotNull($exception);
        $this->assertSame('outbox failed', $exception->getMessage());
        $this->assertDatabaseCount('products', 0);
        $this->assertSame(0, ActivityLog::query()->withoutGlobalScope(BelongsToVenueScope::class)->count());
    }
}
