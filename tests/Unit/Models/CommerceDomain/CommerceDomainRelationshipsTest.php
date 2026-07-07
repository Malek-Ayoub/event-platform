<?php

namespace Tests\Unit\Models\CommerceDomain;

use App\Models\Coupon;
use App\Models\Event;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\TaxRate;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommerceDomainRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function product_belongs_to_venue_and_event_and_has_variants(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $product = Product::factory()->forEvent($event)->create();
        $variant = ProductVariant::factory()->forProduct($product)->create();

        $this->assertTrue($product->venue->is($venue));
        $this->assertTrue($product->event->is($event));
        $this->assertTrue($product->variants->contains($variant));
        $this->assertTrue($event->products->contains($product));
        $this->assertTrue($venue->fresh()->products->contains($product));
    }

    #[Test]
    public function product_variant_belongs_to_product_and_venue(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $product = Product::factory()->create(['venue_id' => $venue->id]);
        $variant = ProductVariant::factory()->forProduct($product)->create();

        $this->assertTrue($variant->product->is($product));
        $this->assertTrue($variant->venue->is($venue));
    }

    #[Test]
    public function coupon_and_promo_code_belong_to_venue(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $coupon = Coupon::factory()->create(['venue_id' => $venue->id]);
        $promoCode = PromoCode::factory()->create(['venue_id' => $venue->id]);

        $this->assertTrue($coupon->venue->is($venue));
        $this->assertTrue($promoCode->venue->is($venue));
        $this->assertTrue($venue->fresh()->coupons->contains($coupon));
        $this->assertTrue($venue->fresh()->promoCodes->contains($promoCode));
    }

    #[Test]
    public function tax_rate_belongs_to_venue(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $taxRate = TaxRate::factory()->create(['venue_id' => $venue->id]);

        $this->assertTrue($taxRate->venue->is($venue));
        $this->assertTrue($venue->fresh()->taxRates->contains($taxRate));
    }

    #[Test]
    public function tenant_scope_filters_commerce_models(): void
    {
        $venueA = Venue::factory()->create();
        $venueB = Venue::factory()->create();

        Product::factory()->create(['venue_id' => $venueA->id]);
        Product::factory()->create(['venue_id' => $venueB->id]);

        $this->bindTenant($venueA->id);

        $this->assertCount(1, Product::query()->get());
        $this->assertCount(
            2,
            Product::query()->withoutGlobalScope(BelongsToVenueScope::class)->get(),
        );
    }
}
