<?php

namespace Tests\Feature\Orders;

use App\Enums\EventDomain\EventStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;

class PublicOrderApiTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
        Cache::flush();
    }

    /**
     * @return array{venue: Venue, event: Event, ticketType: TicketType}
     */
    private function seedPublishedCheckoutContext(string $subdomain = 'guest-checkout'): array
    {
        $venue = Venue::factory()->create(['subdomain' => $subdomain]);
        $this->bindTenant($venue->id);

        $event = Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venue->id,
            'name' => 'Guest Night',
            'slug' => 'guest-night',
        ]);
        $this->attachDefaultPaymentAccount($event);

        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => 50,
            'quantity' => 20,
            'quantity_sold' => 0,
        ]);

        return compact('venue', 'event', 'ticketType');
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(Event $event, TicketType $ticketType, int $quantity = 2): array
    {
        return [
            'event_id' => $event->id,
            'customer_name' => 'Jane Guest',
            'customer_email' => 'jane.guest@example.com',
            'customer_phone' => '+1234567890',
            'line_items' => [
                ['ticket_type_id' => $ticketType->id, 'quantity' => $quantity],
            ],
        ];
    }

    #[Test]
    public function it_creates_a_guest_order_for_a_published_event_without_authentication(): void
    {
        ['venue' => $venue, 'event' => $event, 'ticketType' => $ticketType] = $this->seedPublishedCheckoutContext();

        $response = $this->withTenantHost($venue->subdomain)
            ->postJson('/api/public/orders', $this->validPayload($event, $ticketType));

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Pending->value)
            ->assertJsonPath('data.total', '100.00')
            ->assertJsonPath('data.customer_name', 'Jane Guest')
            ->assertJsonPath('data.customer_email', 'jane.guest@example.com')
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'order_number',
                    'status',
                    'total',
                    'customer_name',
                    'customer_email',
                ],
            ])
            ->assertJsonMissingPath('data.commission_amount')
            ->assertJsonMissingPath('data.payment_account_id')
            ->assertJsonMissingPath('data.subtotal')
            ->assertJsonMissingPath('data.customer_user_id');

        $this->assertDatabaseHas('orders', [
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'customer_email' => 'jane.guest@example.com',
            'customer_user_id' => null,
            'status' => OrderStatus::Pending->value,
        ]);
    }

    #[Test]
    public function it_rejects_orders_for_unpublished_events(): void
    {
        $venue = Venue::factory()->create(['subdomain' => 'draft-checkout']);
        $this->bindTenant($venue->id);

        $draft = Event::factory()->withoutCategory()->create([
            'venue_id' => $venue->id,
            'status' => EventStatus::Draft,
        ]);
        $this->attachDefaultPaymentAccount($draft);
        $ticketType = TicketType::factory()->forEvent($draft)->create(['quantity' => 10]);

        $this->withTenantHost($venue->subdomain)
            ->postJson('/api/public/orders', $this->validPayload($draft, $ticketType))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_id']);

        $archived = Event::factory()->withoutCategory()->create([
            'venue_id' => $venue->id,
            'status' => EventStatus::Completed,
        ]);
        $this->attachDefaultPaymentAccount($archived);
        $archivedTicket = TicketType::factory()->forEvent($archived)->create(['quantity' => 10]);

        $this->withTenantHost($venue->subdomain)
            ->postJson('/api/public/orders', $this->validPayload($archived, $archivedTicket))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_id']);
    }

    #[Test]
    public function it_rejects_ticket_types_that_do_not_belong_to_the_event(): void
    {
        ['venue' => $venue, 'event' => $event] = $this->seedPublishedCheckoutContext('mismatch-tickets');

        $otherEvent = Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venue->id,
        ]);
        $this->attachDefaultPaymentAccount($otherEvent);
        $foreignTicket = TicketType::factory()->forEvent($otherEvent)->create([
            'price' => 25,
            'quantity' => 10,
        ]);

        $this->withTenantHost($venue->subdomain)
            ->postJson('/api/public/orders', [
                'event_id' => $event->id,
                'customer_name' => 'Jane Guest',
                'customer_email' => 'jane.guest@example.com',
                'line_items' => [
                    ['ticket_type_id' => $foreignTicket->id, 'quantity' => 1],
                ],
            ])
            ->assertUnprocessable();
    }

    #[Test]
    public function it_ignores_customer_user_id_even_when_sent_in_the_body(): void
    {
        ['venue' => $venue, 'event' => $event, 'ticketType' => $ticketType] = $this->seedPublishedCheckoutContext('ignore-user');
        $user = User::factory()->create();

        $payload = $this->validPayload($event, $ticketType);
        $payload['customer_user_id'] = $user->id;
        $payload['reservation_id'] = 999;

        $response = $this->withTenantHost($venue->subdomain)
            ->postJson('/api/public/orders', $payload);

        $response->assertCreated();

        $order = Order::query()->findOrFail($response->json('data.id'));
        $this->assertNull($order->customer_user_id);
        $this->assertDatabaseMissing('orders', [
            'id' => $order->id,
            'customer_user_id' => $user->id,
        ]);
    }

    #[Test]
    public function it_does_not_allow_creating_orders_for_another_tenant_event(): void
    {
        $venueA = Venue::factory()->create(['subdomain' => 'tenant-a-orders']);
        $venueB = Venue::factory()->create(['subdomain' => 'tenant-b-orders']);

        $this->bindTenant($venueB->id);
        $eventB = Event::factory()->withoutCategory()->published()->create([
            'venue_id' => $venueB->id,
        ]);
        $this->attachDefaultPaymentAccount($eventB);
        $ticketB = TicketType::factory()->forEvent($eventB)->create(['quantity' => 10]);

        $this->withTenantHost($venueA->subdomain)
            ->postJson('/api/public/orders', $this->validPayload($eventB, $ticketB))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_id']);
    }

    #[Test]
    public function it_rate_limits_guest_order_creation_per_ip(): void
    {
        ['venue' => $venue, 'event' => $event, 'ticketType' => $ticketType] = $this->seedPublishedCheckoutContext('throttled');

        for ($i = 0; $i < 10; $i++) {
            $this->withTenantHost($venue->subdomain)
                ->postJson('/api/public/orders', $this->validPayload($event, $ticketType, 1))
                ->assertCreated();
        }

        $this->withTenantHost($venue->subdomain)
            ->postJson('/api/public/orders', $this->validPayload($event, $ticketType, 1))
            ->assertStatus(429);
    }

    #[Test]
    public function it_reserves_inventory_when_creating_a_guest_order(): void
    {
        ['venue' => $venue, 'event' => $event, 'ticketType' => $ticketType] = $this->seedPublishedCheckoutContext('inventory');

        $this->assertSame(0, $ticketType->fresh()->quantity_sold);

        $this->withTenantHost($venue->subdomain)
            ->postJson('/api/public/orders', $this->validPayload($event, $ticketType, 3))
            ->assertCreated();

        $this->assertSame(3, $ticketType->fresh()->quantity_sold);
    }
}
