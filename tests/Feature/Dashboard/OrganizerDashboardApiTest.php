<?php

namespace Tests\Feature\Dashboard;

use App\Enums\EventDomain\EventStatus;
use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketCheckIn;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Commissions\CommissionPaymentService;
use App\Services\Commissions\Data\RecordCommissionPaymentData;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizerDashboardApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function venue_owner_can_view_dashboard_overview(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;
        $this->bindTenant($venue->id);
        $this->seedDashboardActivity($venue->id);

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.gross_sales', '100.00')
            ->assertJsonPath('data.kpis.net_revenue', '100.00')
            ->assertJsonPath('data.kpis.orders_count', 1)
            ->assertJsonPath('data.kpis.tickets_sold', 1)
            ->assertJsonPath('data.kpis.tickets_remaining', 9)
            ->assertJsonPath('data.kpis.attendance_rate', '100.00')
            ->assertJsonPath('data.kpis.outstanding_commission', '5.00')
            ->assertJsonPath('data.today.today_sales', '100.00')
            ->assertJsonPath('data.today.today_orders', 1)
            ->assertJsonPath('data.today.today_check_ins', 1)
            ->assertJsonPath('data.commission.due', '10.00')
            ->assertJsonPath('data.commission.outstanding', '5.00')
            ->assertJsonCount(1, 'data.events')
            ->assertJsonCount(1, 'data.latest_orders')
            ->assertJsonCount(1, 'data.latest_check_ins')
            ->assertJsonStructure([
                'data' => [
                    'kpis',
                    'today',
                    'events',
                    'latest_orders',
                    'latest_check_ins',
                    'commission',
                    'meta' => ['currency', 'generated_at'],
                ],
            ]);
    }

    #[Test]
    public function empty_venue_returns_zero_defaults_and_empty_lists(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/dashboard')
            ->assertOk()
            ->assertJsonPath('data.kpis.gross_sales', '0.00')
            ->assertJsonPath('data.kpis.net_revenue', '0.00')
            ->assertJsonPath('data.kpis.orders_count', 0)
            ->assertJsonPath('data.kpis.tickets_sold', 0)
            ->assertJsonPath('data.kpis.tickets_remaining', 0)
            ->assertJsonPath('data.kpis.attendance_rate', '0.00')
            ->assertJsonPath('data.kpis.outstanding_commission', '0.00')
            ->assertJsonPath('data.today.today_sales', '0.00')
            ->assertJsonPath('data.today.today_orders', 0)
            ->assertJsonPath('data.today.today_check_ins', 0)
            ->assertJsonPath('data.today.today_revenue', '0.00')
            ->assertJsonPath('data.commission.due', '0.00')
            ->assertJsonPath('data.commission.paid', '0.00')
            ->assertJsonPath('data.commission.outstanding', '0.00')
            ->assertJsonPath('data.events', [])
            ->assertJsonPath('data.latest_orders', [])
            ->assertJsonPath('data.latest_check_ins', [])
            ->assertJsonPath('data.meta.currency', 'USD');
    }

    #[Test]
    public function outsider_without_venue_membership_cannot_view_dashboard(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        $outsider = User::factory()->create();
        $token = $outsider->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/dashboard')
            ->assertForbidden();
    }

    private function seedDashboardActivity(int $venueId): void
    {
        $this->bindTenant($venueId);

        $event = Event::factory()->create([
            'venue_id' => $venueId,
            'name' => 'Tonight Show',
            'status' => EventStatus::Published,
            'start_datetime' => now()->addDays(3),
        ]);

        $ticketType = TicketType::factory()->create([
            'venue_id' => $venueId,
            'event_id' => $event->id,
            'quantity' => 10,
            'quantity_sold' => 1,
        ]);

        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
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

        app(SettlementEntryService::class)->append(new AppendSettlementEntryData(
            venueId: $venueId,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: '10.00',
            currency: 'USD',
            referenceType: 'commission',
            referenceId: 1,
            occurredAt: now(),
        ));

        $admin = User::factory()->superAdmin()->create();
        app(CommissionPaymentService::class)->recordPayment(new RecordCommissionPaymentData(
            venueId: $venueId,
            amount: '5.00',
            currency: 'USD',
            paymentMethod: CommissionPaymentMethod::Cash,
            receivedAt: now()->addMinute(),
            receivedBy: $admin,
        ));
    }
}
