<?php

namespace Tests\Feature\Commerce;

use App\Enums\CommerceDomain\DiscountType;
use App\Models\Coupon;
use App\Models\Event;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PromoCode;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommerceApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
    }

    /**
     * @return array{owner: User, venue: Venue, token: string}
     */
    private function authenticateVenueOwner(): array
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain);

        return ['owner' => $owner, 'venue' => $venue, 'token' => $token];
    }

    #[Test]
    public function owner_can_create_and_list_products(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $create = $this->withToken($token)->postJson('/api/tenant/products', [
            'name' => 'Event Hoodie',
            'description' => 'Warm hoodie',
            'price' => '59.99',
            'is_active' => true,
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.name', 'Event Hoodie')
            ->assertJsonPath('data.price', '59.99');

        $this->withToken($token)->getJson('/api/tenant/products')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        $this->assertDatabaseHas('products', [
            'venue_id' => $venue->id,
            'name' => 'Event Hoodie',
        ]);
    }

    #[Test]
    public function owner_can_manage_product_variants(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $product = Product::factory()->create(['venue_id' => $venue->id, 'price' => '49.99']);

        $create = $this->withToken($token)->postJson("/api/tenant/products/{$product->id}/variants", [
            'name' => 'Large',
            'sku' => 'HD-L',
            'price_override' => '54.99',
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.name', 'Large')
            ->assertJsonPath('data.sku', 'HD-L');

        $variantId = $create->json('data.id');

        $this->withToken($token)->getJson("/api/tenant/products/{$product->id}/variants")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson("/api/tenant/product-variants/{$variantId}")
            ->assertOk()
            ->assertJsonPath('data.price_override', '54.99');

        $this->assertInstanceOf(ProductVariant::class, ProductVariant::query()->find($variantId));
    }

    #[Test]
    public function owner_can_create_product_linked_to_event(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $event = Event::factory()->create(['venue_id' => $venue->id]);

        $this->withToken($token)->postJson('/api/tenant/products', [
            'name' => 'VIP Package Add-on',
            'price' => '99.00',
            'event_id' => $event->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.event_id', $event->id);
    }

    #[Test]
    public function creating_product_linked_to_another_venues_event_fails_validation(): void
    {
        ['token' => $token] = $this->authenticateVenueOwner();

        ['venue' => $otherVenue] = $this->createVenueOwner();
        $foreignEvent = Event::factory()->create(['venue_id' => $otherVenue->id]);

        $this->withToken($token)->postJson('/api/tenant/products', [
            'name' => 'Cross Tenant Add-on',
            'price' => '99.00',
            'event_id' => $foreignEvent->id,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_id']);
    }

    #[Test]
    public function owner_can_manage_coupons_and_promo_codes(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $couponResponse = $this->withToken($token)->postJson('/api/tenant/coupons', [
            'code' => 'save10',
            'discount_type' => DiscountType::Percent->value,
            'discount_value' => '10',
            'max_uses' => 100,
        ]);

        $couponResponse
            ->assertCreated()
            ->assertJsonPath('data.code', 'SAVE10')
            ->assertJsonPath('data.used_count', 0);

        $couponId = $couponResponse->json('data.id');

        $promoResponse = $this->withToken($token)->postJson('/api/tenant/promo-codes', [
            'code' => 'flash5',
            'discount_type' => DiscountType::Fixed->value,
            'discount_value' => '5.00',
        ]);

        $promoResponse
            ->assertCreated()
            ->assertJsonPath('data.code', 'FLASH5');

        $this->withToken($token)->getJson('/api/tenant/coupons')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson('/api/tenant/promo-codes')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->putJson("/api/tenant/coupons/{$couponId}", [
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.is_active', false);

        $this->assertInstanceOf(Coupon::class, Coupon::query()->find($couponId));
        $this->assertInstanceOf(PromoCode::class, PromoCode::query()->find($promoResponse->json('data.id')));
    }

    #[Test]
    public function staff_without_permission_cannot_create_products(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $token = $staff->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->postJson('/api/tenant/products', [
                'name' => 'Blocked',
                'price' => '10.00',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function staff_without_permission_cannot_create_coupons(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $token = $staff->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->postJson('/api/tenant/coupons', [
                'code' => 'NOPE',
                'discount_type' => DiscountType::Percent->value,
                'discount_value' => '5',
            ])
            ->assertForbidden();
    }
}
