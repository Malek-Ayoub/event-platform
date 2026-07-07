<?php

namespace Tests\Unit\Models\CommerceDomain;

use App\Models\Coupon;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommerceSoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function coupon_supports_soft_deletes(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $coupon = Coupon::factory()->create(['venue_id' => $venue->id]);
        $couponId = $coupon->id;

        $coupon->delete();

        $this->assertSoftDeleted('coupons', ['id' => $couponId]);
        $this->assertNull(Coupon::query()->find($couponId));
        $this->assertNotNull(Coupon::withTrashed()->find($couponId));
    }

    #[Test]
    public function promo_code_supports_soft_deletes(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $promoCode = PromoCode::factory()->create(['venue_id' => $venue->id]);
        $promoCodeId = $promoCode->id;

        $promoCode->delete();

        $this->assertSoftDeleted('promo_codes', ['id' => $promoCodeId]);
        $this->assertNull(PromoCode::query()->find($promoCodeId));
        $this->assertNotNull(PromoCode::withTrashed()->find($promoCodeId));
    }
}
