<?php

namespace Tests\Unit\Models\CommerceDomain;

use App\Enums\CommerceDomain\DiscountType;
use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\TaxRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommerceDomainCastsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function product_casts_price_and_boolean_fields(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $product = Product::factory()->create([
            'venue_id' => $venue->id,
            'price' => '49.99',
            'is_active' => 1,
        ]);

        $this->assertSame('49.99', $product->price);
        $this->assertIsBool($product->is_active);
    }

    #[Test]
    public function product_variant_casts_price_override(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $product = Product::factory()->create(['venue_id' => $venue->id]);
        $variant = ProductVariant::factory()->forProduct($product)->create([
            'price_override' => '19.50',
        ]);

        $this->assertSame('19.50', $variant->price_override);
    }

    #[Test]
    public function coupon_and_promo_code_cast_discount_type_enum(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $coupon = Coupon::factory()->create([
            'venue_id' => $venue->id,
            'discount_type' => DiscountType::Percent,
        ]);
        $promoCode = PromoCode::factory()->create([
            'venue_id' => $venue->id,
            'discount_type' => DiscountType::Fixed,
        ]);

        $this->assertSame(DiscountType::Percent, $coupon->discount_type);
        $this->assertSame(DiscountType::Fixed, $promoCode->discount_type);
    }

    #[Test]
    public function tax_rate_casts_rate_and_version(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $taxRate = TaxRate::factory()->create([
            'venue_id' => $venue->id,
            'rate' => '0.1500',
            'version' => 1,
        ]);

        $this->assertSame('0.1500', $taxRate->rate);
        $this->assertSame(1, $taxRate->version);
    }
}
