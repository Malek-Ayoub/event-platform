<?php

namespace Tests\Unit\Models\CommerceDomain;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommerceDomainScopesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function active_scopes_filter_inactive_commerce_records(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        Product::factory()->create(['venue_id' => $venue->id, 'is_active' => true]);
        Product::factory()->inactive()->create(['venue_id' => $venue->id]);

        $product = Product::query()->active()->first();
        ProductVariant::factory()->forProduct($product)->create(['is_active' => true]);
        ProductVariant::factory()->forProduct($product)->inactive()->create();

        Coupon::factory()->create(['venue_id' => $venue->id, 'is_active' => true]);
        Coupon::factory()->inactive()->create(['venue_id' => $venue->id]);

        PromoCode::factory()->create(['venue_id' => $venue->id, 'is_active' => true]);
        PromoCode::factory()->inactive()->create(['venue_id' => $venue->id]);

        TaxRate::factory()->create(['venue_id' => $venue->id, 'is_active' => true]);
        TaxRate::factory()->inactive()->create(['venue_id' => $venue->id]);

        $this->assertCount(1, Product::query()->active()->get());
        $this->assertCount(1, ProductVariant::query()->active()->get());
        $this->assertCount(1, Coupon::query()->active()->get());
        $this->assertCount(1, PromoCode::query()->active()->get());
        $this->assertCount(1, TaxRate::query()->active()->get());
    }
}
