<?php

namespace Tests\Feature\Events;

use App\Enums\EventDomain\EventStatus;
use App\Models\Event;
use App\Models\PlatformSetting;
use App\Models\TicketType;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicEventApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
    }

    private function seedPlatformCurrency(string $currency = 'USD'): void
    {
        PlatformSetting::factory()->create([
            'settings' => [
                'default_currency' => $currency,
            ],
        ]);
    }

    #[Test]
    public function it_lists_published_events_without_authentication(): void
    {
        $this->seedPlatformCurrency();

        $venue = Venue::factory()->create(['subdomain' => 'public-catalog']);
        $this->withTenantHost($venue->subdomain);

        $published = Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venue->id,
            'name' => 'Published Show',
            'slug' => 'published-show',
        ]);

        Event::factory()->withoutCategory()->create([
            'venue_id' => $venue->id,
            'name' => 'Draft Show',
            'status' => EventStatus::Draft,
        ]);

        $response = $this->getJson('/api/public/events');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $published->id)
            ->assertJsonPath('data.0.title', 'Published Show')
            ->assertJsonPath('data.0.slug', 'published-show')
            ->assertJsonPath('data.0.venue', $venue->name)
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'slug',
                    'title',
                    'description',
                    'venue',
                    'image_url',
                    'starts_at',
                    'starting_price',
                ]],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);
    }

    #[Test]
    public function it_scopes_results_to_the_current_tenant_subdomain(): void
    {
        $this->seedPlatformCurrency();

        $venueA = Venue::factory()->create(['subdomain' => 'venue-a']);
        $venueB = Venue::factory()->create(['subdomain' => 'venue-b']);

        Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venueA->id,
            'name' => 'Venue A Event',
        ]);

        Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venueB->id,
            'name' => 'Venue B Event',
        ]);

        $this->withTenantHost($venueA->subdomain)
            ->getJson('/api/public/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Venue A Event');
    }

    #[Test]
    public function it_returns_starting_price_from_lowest_available_ticket_type(): void
    {
        $this->seedPlatformCurrency('EUR');

        $venue = Venue::factory()->create(['subdomain' => 'priced-events']);
        $this->bindTenant($venue->id);
        $event = Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venue->id,
        ]);

        TicketType::factory()->forEvent($event)->create([
            'name' => 'VIP',
            'price' => '75.00',
            'quantity' => 50,
            'quantity_sold' => 0,
            'sale_start' => now()->subDay(),
            'sale_end' => now()->addMonth(),
        ]);

        TicketType::factory()->forEvent($event)->create([
            'name' => 'General',
            'price' => '45.00',
            'quantity' => 100,
            'quantity_sold' => 10,
            'sale_start' => now()->subDay(),
            'sale_end' => now()->addMonth(),
        ]);

        TicketType::factory()->forEvent($event)->create([
            'name' => 'Sold Out',
            'price' => '10.00',
            'quantity' => 5,
            'quantity_sold' => 5,
            'sale_start' => now()->subDay(),
            'sale_end' => now()->addMonth(),
        ]);

        $this->withTenantHost($venue->subdomain)
            ->getJson('/api/public/events')
            ->assertOk()
            ->assertJsonPath('data.0.starting_price.amount', '45.00')
            ->assertJsonPath('data.0.starting_price.currency', 'EUR');
    }

    #[Test]
    public function it_returns_null_starting_price_when_no_ticket_types_exist(): void
    {
        $this->seedPlatformCurrency();

        $venue = Venue::factory()->create(['subdomain' => 'no-tickets']);
        Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venue->id,
        ]);

        $this->withTenantHost($venue->subdomain)
            ->getJson('/api/public/events')
            ->assertOk()
            ->assertJsonPath('data.0.starting_price', null);
    }

    #[Test]
    public function it_returns_null_starting_price_when_all_ticket_types_are_sold_out(): void
    {
        $this->seedPlatformCurrency();

        $venue = Venue::factory()->create(['subdomain' => 'sold-out']);
        $this->bindTenant($venue->id);
        $event = Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venue->id,
        ]);

        TicketType::factory()->forEvent($event)->create([
            'price' => '30.00',
            'quantity' => 10,
            'quantity_sold' => 10,
        ]);

        $this->withTenantHost($venue->subdomain)
            ->getJson('/api/public/events')
            ->assertOk()
            ->assertJsonPath('data.0.starting_price', null);
    }

    #[Test]
    public function it_returns_null_starting_price_when_only_free_ticket_types_are_available(): void
    {
        $this->seedPlatformCurrency();

        $venue = Venue::factory()->create(['subdomain' => 'free-event']);
        $this->bindTenant($venue->id);
        $event = Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venue->id,
        ]);

        TicketType::factory()->forEvent($event)->create([
            'price' => '0.00',
            'quantity' => 100,
            'quantity_sold' => 0,
        ]);

        $this->withTenantHost($venue->subdomain)
            ->getJson('/api/public/events')
            ->assertOk()
            ->assertJsonPath('data.0.starting_price', null);
    }
}
