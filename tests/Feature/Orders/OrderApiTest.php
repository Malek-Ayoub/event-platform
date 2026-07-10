<?php

namespace Tests\Feature\Orders;

use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;

class OrderApiTest extends TestCase
{
    use InteractsWithPaymentFlows;
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
        $this->bindTenant($venue->id);

        return ['owner' => $owner, 'venue' => $venue, 'token' => $token];
    }

    #[Test]
    public function owner_can_create_and_show_order(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'price' => 75,
            'quantity' => 10,
        ]);

        $create = $this->withToken($token)->postJson('/api/tenant/orders', [
            'event_id' => $event->id,
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'customer_phone' => '+1234567890',
            'line_items' => [
                ['ticket_type_id' => $ticketType->id, 'quantity' => 2],
            ],
        ]);

        $create
            ->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::Pending->value)
            ->assertJsonPath('data.subtotal', '150.00')
            ->assertJsonPath('data.total', '150.00');

        $this->assertNull($create->json('data.tickets'));

        $orderId = $create->json('data.id');

        $this->withToken($token)->getJson("/api/tenant/orders/{$orderId}")
            ->assertOk()
            ->assertJsonPath('data.order_number', $create->json('data.order_number'))
            ->assertJsonCount(0, 'data.tickets');

        $this->assertDatabaseHas('orders', [
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'customer_email' => 'jane@example.com',
        ]);
    }

    #[Test]
    public function owner_can_list_orders_with_filters(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 5]);

        $this->withToken($token)->postJson('/api/tenant/orders', [
            'event_id' => $event->id,
            'customer_name' => 'Buyer',
            'customer_email' => 'buyer@example.com',
            'line_items' => [
                ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
            ],
        ])->assertCreated();

        $this->withToken($token)->getJson('/api/tenant/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        $this->withToken($token)->getJson('/api/tenant/orders?status=pending&event_id='.$event->id)
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson('/api/tenant/orders?status=paid')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function create_order_fails_when_insufficient_tickets(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create([
            'quantity' => 1,
            'quantity_sold' => 1,
        ]);

        $this->withToken($token)->postJson('/api/tenant/orders', [
            'event_id' => $event->id,
            'customer_name' => 'Buyer',
            'customer_email' => 'buyer@example.com',
            'line_items' => [
                ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
            ],
        ])->assertUnprocessable();
    }

    #[Test]
    public function staff_with_default_permissions_can_create_orders(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $token = $staff->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain);
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 5]);

        $this->withToken($token)
            ->postJson('/api/tenant/orders', [
                'event_id' => $event->id,
                'customer_name' => 'Staff Order',
                'customer_email' => 'staff-order@example.com',
                'line_items' => [
                    ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
                ],
            ])
            ->assertCreated();
    }

    #[Test]
    public function customer_without_venue_membership_cannot_create_orders(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        $customer = User::factory()->create();
        $token = $customer->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain);
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $this->attachDefaultPaymentAccount($event);
        $ticketType = TicketType::factory()->forEvent($event)->create(['quantity' => 5]);

        $this->withToken($token)
            ->postJson('/api/tenant/orders', [
                'event_id' => $event->id,
                'customer_name' => 'Blocked',
                'customer_email' => 'blocked@example.com',
                'line_items' => [
                    ['ticket_type_id' => $ticketType->id, 'quantity' => 1],
                ],
            ])
            ->assertForbidden();
    }

    #[Test]
    public function owner_can_view_existing_order(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create(['venue_id' => $venue->id]);

        $this->withToken($token)->getJson("/api/tenant/orders/{$order->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $order->id)
            ->assertJsonPath('data.order_number', $order->order_number);
    }

    #[Test]
    public function creating_order_with_another_venues_event_fails_validation(): void
    {
        ['token' => $token] = $this->authenticateVenueOwner();

        ['venue' => $otherVenue] = $this->createVenueOwner();
        $foreignEvent = Event::factory()->create(['venue_id' => $otherVenue->id]);
        $foreignTicketType = TicketType::factory()->forEvent($foreignEvent)->create(['quantity' => 5]);

        $this->withToken($token)->postJson('/api/tenant/orders', [
            'event_id' => $foreignEvent->id,
            'customer_name' => 'Cross Tenant',
            'customer_email' => 'cross-tenant@example.com',
            'line_items' => [
                ['ticket_type_id' => $foreignTicketType->id, 'quantity' => 1],
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['event_id', 'line_items.0.ticket_type_id']);
    }
}
