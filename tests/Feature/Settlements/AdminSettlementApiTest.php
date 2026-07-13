<?php

namespace Tests\Feature\Settlements;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\User;
use App\Models\Venue;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminSettlementApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_list_venue_settlements_and_view_statement(): void
    {
        $venueA = Venue::factory()->create(['name' => 'Alpha Venue']);
        $venueB = Venue::factory()->create(['name' => 'Beta Venue']);
        $this->seedDue($venueA->id, '20.00');
        $this->seedDue($venueB->id, '40.00');

        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/admin/settlement/venues?sort=outstanding&direction=desc')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.venue_name', 'Beta Venue')
            ->assertJsonPath('data.0.outstanding_commission', '40.00')
            ->assertJsonStructure(['data', 'meta' => ['pagination', 'from', 'to']]);

        $this->withToken($token)
            ->getJson("/api/admin/venues/{$venueA->id}/settlement")
            ->assertOk()
            ->assertJsonPath('data.summary.commission_due', '20.00')
            ->assertJsonPath('data.summary.commission_outstanding', '20.00')
            ->assertJsonCount(1, 'data.entries')
            ->assertJsonCount(0, 'data.payments')
            ->assertJsonStructure([
                'data' => ['summary', 'entries', 'payments', 'meta'],
            ]);
    }

    #[Test]
    public function venue_owner_cannot_access_admin_settlement_endpoints(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withToken($token)->getJson('/api/admin/settlement/venues')->assertForbidden();
        $this->withToken($token)->getJson("/api/admin/venues/{$venue->id}/settlement")->assertForbidden();
    }

    private function seedDue(int $venueId, string $amount): void
    {
        $this->bindTenant($venueId);

        $event = Event::factory()->create(['venue_id' => $venueId]);
        $order = Order::factory()->forEvent($event)->create([
            'total' => '200.00',
            'status' => OrderStatus::Paid,
            'updated_at' => now(),
        ]);

        app(SettlementEntryService::class)->append(new AppendSettlementEntryData(
            venueId: $venueId,
            eventId: $event->id,
            orderId: $order->id,
            type: SettlementEntryType::CommissionDue,
            direction: SettlementEntryDirection::Credit,
            amount: $amount,
            currency: 'USD',
            referenceType: 'commission',
            referenceId: fake()->unique()->numberBetween(1000, 9999),
            occurredAt: now(),
        ));
    }
}
