<?php

namespace Tests\Feature\Reports;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\FinancialDomain\RefundStatus;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Venue;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminReportApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_view_platform_report(): void
    {
        $venueA = Venue::factory()->create(['name' => 'Alpha Venue']);
        $venueB = Venue::factory()->create(['name' => 'Beta Venue']);
        $this->seedVenueActivity($venueA, '300.00', 'Alpha Event');
        $this->seedVenueActivity($venueB, '100.00', 'Beta Event');

        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/reports?limit=1')
            ->assertOk()
            ->assertJsonPath('data.platform.gross_revenue', '400.00')
            ->assertJsonPath('data.platform.orders_count', 2)
            ->assertJsonPath('data.platform.tickets_sold', 2)
            ->assertJsonPath('data.platform.active_venues', 2)
            ->assertJsonPath('data.commissions.commission_due', '40.00')
            ->assertJsonPath('data.refunds.refunded_amount', '0.00')
            ->assertJsonCount(1, 'data.top_venues')
            ->assertJsonPath('data.top_venues.0.venue_name', 'Alpha Venue')
            ->assertJsonCount(1, 'data.top_events')
            ->assertJsonPath('data.meta.limit', 1)
            ->assertJsonStructure([
                'data' => [
                    'platform',
                    'commissions' => ['monthly'],
                    'top_venues',
                    'top_events',
                    'payment_methods',
                    'refunds',
                    'meta',
                ],
            ]);
    }

    #[Test]
    public function venue_owner_cannot_view_admin_report(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/reports')
            ->assertForbidden();
    }

    private function seedVenueActivity(Venue $venue, string $total, string $eventName): void
    {
        $this->bindTenant($venue->id);

        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => $eventName,
        ]);

        $order = Order::factory()->forEvent($event)->create([
            'total' => $total,
            'status' => OrderStatus::Paid,
            'updated_at' => now(),
        ]);

        Ticket::factory()->forOrder($order)->create([
            'status' => TicketStatus::Issued,
            'issued_at' => now(),
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
