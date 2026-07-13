<?php

namespace Tests\Feature\Settlements;

use App\Enums\FinancialDomain\CommissionPaymentMethod;
use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\CommissionPayment;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Services\Commissions\CommissionPaymentService;
use App\Services\Commissions\Data\RecordCommissionPaymentData;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrganizerSettlementApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function venue_owner_can_view_settlement_summary_and_entries(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;
        $this->bindTenant($venue->id);
        $this->seedSettlementActivity($venue->id);

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/settlement/summary')
            ->assertOk()
            ->assertJsonPath('data.summary.gross_sales', '100.00')
            ->assertJsonPath('data.summary.commission_due', '10.00')
            ->assertJsonPath('data.summary.commission_outstanding', '5.00')
            ->assertJsonPath('data.entries', [])
            ->assertJsonPath('data.payments', [])
            ->assertJsonStructure([
                'data' => ['summary', 'entries', 'payments', 'meta'],
            ]);

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/settlement/entries')
            ->assertJsonCount(2, 'data.entries')
            ->assertJsonPath('data.entries.0.type', 'commission_due')
            ->assertJsonPath('data.entries.1.type', 'commission_paid')
            ->assertJsonPath('data.entries.1.balance', '5.00')
            ->assertJsonPath('data.summary', [])
            ->assertJsonPath('data.payments', []);
    }

    #[Test]
    public function outsider_without_venue_membership_cannot_view_settlement(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        $outsider = User::factory()->create();
        $token = $outsider->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain)
            ->withToken($token)
            ->getJson('/api/tenant/organizer/settlement/summary')
            ->assertForbidden();
    }

    private function seedSettlementActivity(int $venueId): void
    {
        $this->bindTenant($venueId);

        $event = Event::factory()->create(['venue_id' => $venueId]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '100.00',
            'status' => OrderStatus::Paid,
            'updated_at' => now(),
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

        $this->assertSame(1, CommissionPayment::query()->count());
    }
}
