<?php

namespace Tests\Feature\Commissions;

use App\Enums\FinancialDomain\SettlementEntryDirection;
use App\Enums\FinancialDomain\SettlementEntryType;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\SettlementEntry;
use App\Models\User;
use App\Services\Settlements\Data\AppendSettlementEntryData;
use App\Services\Settlements\SettlementEntryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CommissionPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function super_admin_can_record_commission_payment(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;
        $this->seedOutstandingCommission($venue->id, '40.00');
        $account = PaymentAccount::factory()->create(['venue_id' => $venue->id]);

        $this->withToken($token)->postJson('/api/admin/commission-payments', [
            'venue_id' => $venue->id,
            'amount' => '20.00',
            'currency' => 'USD',
            'payment_method' => 'shamcash',
            'reference_number' => 'SC-7788',
            'received_at' => '2026-07-13T12:00:00Z',
            'payment_account_id' => $account->id,
            'notes' => 'Manual transfer received',
        ])
            ->assertCreated()
            ->assertJsonPath('data.amount', '20.00')
            ->assertJsonPath('data.payment_method', 'shamcash')
            ->assertJsonPath('data.outstanding_commission', '20.00')
            ->assertJsonPath('data.received_by_user_id', $admin->id);

        $this->assertSame(
            1,
            SettlementEntry::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('type', SettlementEntryType::CommissionPaid)
                ->count(),
        );
    }

    #[Test]
    public function venue_owner_cannot_record_commission_payment(): void
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;
        $this->seedOutstandingCommission($venue->id, '40.00');

        $this->withToken($token)->postJson('/api/admin/commission-payments', [
            'venue_id' => $venue->id,
            'amount' => '20.00',
            'payment_method' => 'cash',
            'received_at' => now()->toIso8601String(),
        ])->assertForbidden();
    }

    #[Test]
    public function api_rejects_payment_above_outstanding_commission(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $admin = User::factory()->superAdmin()->create();
        $token = $admin->createToken('api')->plainTextToken;
        $this->seedOutstandingCommission($venue->id, '40.00');

        $this->withToken($token)->postJson('/api/admin/commission-payments', [
            'venue_id' => $venue->id,
            'amount' => '60.00',
            'payment_method' => 'cash',
            'received_at' => now()->toIso8601String(),
        ])->assertStatus(422);
    }

    private function seedOutstandingCommission(int $venueId, string $amount): void
    {
        $this->bindTenant($venueId);

        $event = Event::factory()->create(['venue_id' => $venueId]);
        $order = Order::factory()->forEvent($event)->create();

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
