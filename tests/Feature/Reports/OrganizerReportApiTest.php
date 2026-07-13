<?php

namespace Tests\Feature\Reports;

use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Commissions\CommissionPaymentService;
use App\Services\Commissions\Data\RecordCommissionPaymentData;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizerReportApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function venue_owner_can_view_organizer_report(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;
        $this->bindTenant($venue->id);
        $event = $this->seedReportActivity($venue->id);

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/reports')
            ->assertOk()
            ->assertJsonPath('data.sales.gross_sales', '100.00')
            ->assertJsonPath('data.sales.orders_count', 1)
            ->assertJsonPath('data.sales.tickets_sold', 1)
            ->assertJsonPath('data.sales.average_order_value', '100.00')
            ->assertJsonPath('data.revenue.net_revenue', '100.00')
            ->assertJsonPath('data.attendance.checked_in', 1)
            ->assertJsonPath('data.attendance.attendance_rate', '100.00')
            ->assertJsonPath('data.commission.commission_due', '10.00')
            ->assertJsonPath('data.commission.outstanding_commission', '5.00')
            ->assertJsonPath('data.meta.currency', 'USD')
            ->assertJsonPath('data.meta.event_id', null)
            ->assertJsonStructure([
                'data' => ['sales', 'revenue', 'attendance', 'commission', 'meta'],
            ]);

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/reports?event_id='.$event->id)
            ->assertOk()
            ->assertJsonPath('data.meta.event_id', $event->id)
            ->assertJsonPath('data.sales.gross_sales', '100.00');
    }

    #[Test]
    public function outsider_without_venue_membership_cannot_view_organizer_report(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        $outsider = User::factory()->create();
        $token = $outsider->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/reports')
            ->assertForbidden();
    }

    #[Test]
    public function unknown_event_returns_not_found(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/reports?event_id=99999')
            ->assertNotFound();
    }

    private function seedReportActivity(int $venueId): Event
    {
        $this->bindTenant($venueId);

        $event = Event::factory()->create(['venue_id' => $venueId]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'status' => OrderStatus::Paid,
            'updated_at' => now(),
        ]);

        Ticket::factory()->forOrder($order)->create([
            'status' => TicketStatus::CheckedIn,
            'issued_at' => now(),
            'checked_in_at' => now(),
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

        return $event;
    }
}
