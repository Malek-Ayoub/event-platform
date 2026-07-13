<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventDomain\EventStatus;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Ticket;
use App\Models\TicketCheckIn;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_view_platform_dashboard(): void
    {
        $venueA = Venue::factory()->create(['name' => 'Alpha Venue']);
        $venueB = Venue::factory()->create(['name' => 'Beta Venue']);
        $this->seedVenueActivity($venueA, '300.00', 'Alpha Event', startsToday: true);
        $this->seedVenueActivity($venueB, '100.00', 'Beta Event');

        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.gross_revenue', '400.00')
            ->assertJsonPath('data.kpis.net_revenue', '400.00')
            ->assertJsonPath('data.kpis.commission_due', '40.00')
            ->assertJsonPath('data.kpis.outstanding_commission', '40.00')
            ->assertJsonPath('data.kpis.active_events', 2)
            ->assertJsonPath('data.kpis.active_venues', 2)
            ->assertJsonPath('data.kpis.orders_count', 2)
            ->assertJsonPath('data.kpis.tickets_sold', 2)
            ->assertJsonPath('data.today.today_sales', '400.00')
            ->assertJsonPath('data.today.today_orders', 2)
            ->assertJsonPath('data.today.today_check_ins', 2)
            ->assertJsonPath('data.today.events_starting_today', 1)
            ->assertJsonCount(2, 'data.top_venues')
            ->assertJsonPath('data.top_venues.0.venue_name', 'Alpha Venue')
            ->assertJsonCount(2, 'data.top_events')
            ->assertJsonCount(2, 'data.latest_orders')
            ->assertJsonCount(2, 'data.latest_payments')
            ->assertJsonCount(2, 'data.latest_check_ins')
            ->assertJsonCount(3, 'data.alerts')
            ->assertJsonPath('data.alerts.0.type', 'outstanding_commission')
            ->assertJsonPath('data.alerts.0.count', 2)
            ->assertJsonPath('data.alerts.1.type', 'events_starting_today')
            ->assertJsonPath('data.alerts.1.count', 1)
            ->assertJsonStructure([
                'data' => [
                    'kpis',
                    'today',
                    'top_venues',
                    'top_events',
                    'latest_orders',
                    'latest_payments',
                    'latest_check_ins',
                    'alerts',
                    'meta' => ['currency', 'generated_at'],
                ],
            ]);
    }

    #[Test]
    public function empty_platform_returns_zero_defaults_and_empty_lists(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.gross_revenue', '0.00')
            ->assertJsonPath('data.kpis.net_revenue', '0.00')
            ->assertJsonPath('data.kpis.commission_due', '0.00')
            ->assertJsonPath('data.kpis.commission_paid', '0.00')
            ->assertJsonPath('data.kpis.outstanding_commission', '0.00')
            ->assertJsonPath('data.kpis.active_events', 0)
            ->assertJsonPath('data.kpis.active_venues', 0)
            ->assertJsonPath('data.kpis.orders_count', 0)
            ->assertJsonPath('data.kpis.tickets_sold', 0)
            ->assertJsonPath('data.today.today_sales', '0.00')
            ->assertJsonPath('data.today.today_revenue', '0.00')
            ->assertJsonPath('data.today.today_orders', 0)
            ->assertJsonPath('data.today.today_check_ins', 0)
            ->assertJsonPath('data.today.events_starting_today', 0)
            ->assertJsonPath('data.top_venues', [])
            ->assertJsonPath('data.top_events', [])
            ->assertJsonPath('data.latest_orders', [])
            ->assertJsonPath('data.latest_payments', [])
            ->assertJsonPath('data.latest_check_ins', [])
            ->assertJsonCount(3, 'data.alerts')
            ->assertJsonPath('data.alerts.0.count', 0)
            ->assertJsonPath('data.alerts.0.amount', '0.00')
            ->assertJsonPath('data.alerts.1.count', 0)
            ->assertJsonPath('data.alerts.2.count', 0)
            ->assertJsonPath('data.meta.currency', 'USD');
    }

    #[Test]
    public function venue_owner_cannot_view_admin_dashboard(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/dashboard')
            ->assertForbidden();
    }

    private function seedVenueActivity(
        Venue $venue,
        string $total,
        string $eventName,
        bool $startsToday = false,
    ): void {
        $this->bindTenant($venue->id);

        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => $eventName,
            'status' => EventStatus::Published,
            'start_datetime' => $startsToday ? now()->addHours(2) : now()->addDays(3),
        ]);

        $ticketType = TicketType::factory()->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'quantity' => 10,
            'quantity_sold' => 1,
        ]);

        $order = Order::factory()->forEvent($event)->create([
            'total' => $total,
            'status' => OrderStatus::Paid,
            'customer_name' => 'Layla Hassan',
            'updated_at' => now(),
            'created_at' => now(),
        ]);

        $ticket = Ticket::factory()
            ->forOrder($order)
            ->forTicketType($ticketType)
            ->create([
                'status' => TicketStatus::CheckedIn,
                'issued_at' => now(),
                'checked_in_at' => now(),
            ]);

        TicketCheckIn::factory()->forTicket($ticket)->create([
            'checked_in_at' => now(),
            'gate_id' => 1,
        ]);

        PaymentTransaction::factory()->forOrder($order)->paid()->create([
            'provider' => 'shamcash',
            'amount' => $total,
            'updated_at' => now(),
        ]);

        app(SettlementEntryService::class)->append(new AppendSettlementEntryData(
            venueId: $venue->id,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: bcdiv($total, '10', 2),
            currency: 'USD',
            referenceType: 'commission',
            referenceId: $order->id,
            occurredAt: now(),
        ));
    }
}
