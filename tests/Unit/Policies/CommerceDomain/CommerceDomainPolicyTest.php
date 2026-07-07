<?php

namespace Tests\Unit\Policies\CommerceDomain;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Venue;
use App\Policies\CouponPolicy;
use App\Policies\ProductPolicy;
use App\Policies\ProductVariantPolicy;
use App\Policies\PromoCodePolicy;
use App\Policies\TaxRatePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\SeedsPermissions;
use Tests\TestCase;

class CommerceDomainPolicyTest extends TestCase
{
    use RefreshDatabase, SeedsPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissionsCatalog();
    }

    #[Test]
    public function super_admin_can_manage_all_commerce_resources(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $fixtures = $this->createCommerceFixtures();

        $this->assertTrue(app(ProductPolicy::class)->update($admin, $fixtures['product']));
        $this->assertTrue(app(ProductVariantPolicy::class)->update($admin, $fixtures['variant']));
        $this->assertTrue(app(CouponPolicy::class)->update($admin, $fixtures['coupon']));
        $this->assertTrue(app(PromoCodePolicy::class)->update($admin, $fixtures['promoCode']));
        $this->assertTrue(app(TaxRatePolicy::class)->update($admin, $fixtures['taxRate']));
    }

    #[Test]
    public function owner_can_manage_commerce_resources_in_own_venue(): void
    {
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);
        $fixtures = $this->createCommerceFixtures($venue);

        $this->assertTrue(app(ProductPolicy::class)->create($owner));
        $this->assertTrue(app(ProductPolicy::class)->update($owner, $fixtures['product']));
        $this->assertTrue(app(CouponPolicy::class)->update($owner, $fixtures['coupon']));
        $this->assertTrue(app(TaxRatePolicy::class)->update($owner, $fixtures['taxRate']));
    }

    #[Test]
    public function staff_can_view_but_cannot_manage_products_or_discounts(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $this->bindTenant($venue->id);
        $fixtures = $this->createCommerceFixtures($venue);

        $this->assertTrue(app(ProductPolicy::class)->view($staff, $fixtures['product']));
        $this->assertFalse(app(ProductPolicy::class)->update($staff, $fixtures['product']));
        $this->assertFalse(app(CouponPolicy::class)->update($staff, $fixtures['coupon']));
        $this->assertFalse(app(TaxRatePolicy::class)->update($staff, $fixtures['taxRate']));
    }

    #[Test]
    public function customer_cannot_access_commerce_resources(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $customer = User::factory()->create();
        $this->bindTenant($venue->id);
        $fixtures = $this->createCommerceFixtures($venue);

        $this->assertFalse(app(ProductPolicy::class)->view($customer, $fixtures['product']));
        $this->assertFalse(app(ProductPolicy::class)->create($customer));
        $this->assertFalse(app(CouponPolicy::class)->view($customer, $fixtures['coupon']));
        $this->assertFalse(app(TaxRatePolicy::class)->view($customer, $fixtures['taxRate']));
    }

    #[Test]
    public function owner_cannot_manage_commerce_resources_from_another_tenant(): void
    {
        ['user' => $ownerA] = $this->createVenueOwner();
        ['venue' => $venueB] = $this->createVenueOwner();
        $this->bindTenant($venueB->id);
        $fixturesB = $this->createCommerceFixtures($venueB);

        $this->assertFalse(app(ProductPolicy::class)->view($ownerA, $fixturesB['product']));
        $this->assertFalse(app(CouponPolicy::class)->update($ownerA, $fixturesB['coupon']));
        $this->assertFalse(app(TaxRatePolicy::class)->update($ownerA, $fixturesB['taxRate']));
    }

    /**
     * @return array{
     *     product: Product,
     *     variant: ProductVariant,
     *     coupon: Coupon,
     *     promoCode: PromoCode,
     *     taxRate: TaxRate
     * }
     */
    private function createCommerceFixtures(?Venue $venue = null): array
    {
        if ($venue === null) {
            ['venue' => $venue] = $this->createVenueOwner();
            $this->bindTenant($venue->id);
        }

        $product = Product::factory()->create(['venue_id' => $venue->id]);
        $variant = ProductVariant::factory()->forProduct($product)->create();
        $coupon = Coupon::factory()->create(['venue_id' => $venue->id]);
        $promoCode = PromoCode::factory()->create(['venue_id' => $venue->id]);
        $taxRate = TaxRate::factory()->create(['venue_id' => $venue->id]);

        return [
            'product' => $product,
            'variant' => $variant,
            'coupon' => $coupon,
            'promoCode' => $promoCode,
            'taxRate' => $taxRate,
        ];
    }
}
