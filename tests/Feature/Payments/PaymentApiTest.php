<?php

namespace Tests\Feature\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentApiTest extends TestCase
{
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

    /**
     * @return array{order: Order, event: Event}
     */
    private function createPendingOrder(Venue $venue): array
    {
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '120.00',
            'subtotal' => '120.00',
            'status' => OrderStatus::Pending,
        ]);

        return ['order' => $order, 'event' => $event];
    }

    #[Test]
    public function owner_can_initiate_complete_and_show_payment(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();
        ['order' => $order] = $this->createPendingOrder($venue);

        $initiate = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'shamcash',
            'provider_transaction_id' => 'TXN-API-001',
            'amount' => '120.00',
            'currency' => 'USD',
        ]);

        $initiate
            ->assertCreated()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Pending->value)
            ->assertJsonPath('data.provider_transaction_id', 'TXN-API-001');

        $paymentId = $initiate->json('data.id');

        $this->withToken($token)->postJson("/api/tenant/payments/{$paymentId}/complete", [
            'payment_method' => 'shamcash',
            'payment_reference' => 'REF-001',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Completed->value);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $this->withToken($token)->getJson("/api/tenant/payments/{$paymentId}")
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.status', PaymentTransactionStatus::Completed->value);
    }

    #[Test]
    public function owner_can_list_payments_with_filters(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();
        ['order' => $order] = $this->createPendingOrder($venue);

        PaymentTransaction::factory()->forOrder($order)->create([
            'amount' => '120.00',
            'provider_transaction_id' => 'TXN-LIST-1',
        ]);

        $this->withToken($token)->getJson('/api/tenant/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        $this->withToken($token)->getJson('/api/tenant/payments?order_id='.$order->id.'&status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson('/api/tenant/payments?status=completed')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function owner_can_fail_payment(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();
        ['order' => $order] = $this->createPendingOrder($venue);

        $payment = PaymentTransaction::factory()->forOrder($order)->create([
            'amount' => '120.00',
            'provider_transaction_id' => 'TXN-FAIL-1',
        ]);

        $this->withToken($token)->postJson("/api/tenant/payments/{$payment->id}/fail", [
            'reason' => 'Provider declined',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Failed->value);

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    #[Test]
    public function initiate_payment_rejects_amount_mismatch(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();
        ['order' => $order] = $this->createPendingOrder($venue);

        $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'shamcash',
            'provider_transaction_id' => 'TXN-BAD-AMT',
            'amount' => '99.00',
        ])->assertStatus(500);
    }

    #[Test]
    public function complete_payment_is_idempotent(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();
        ['order' => $order] = $this->createPendingOrder($venue);

        $payment = PaymentTransaction::factory()->forOrder($order)->completed()->create([
            'amount' => '120.00',
            'provider_transaction_id' => 'TXN-IDEM-1',
        ]);

        $order->update(['status' => OrderStatus::Paid]);

        $this->withToken($token)->postJson("/api/tenant/payments/{$payment->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Completed->value);
    }

    #[Test]
    public function customer_without_venue_membership_cannot_initiate_payment(): void
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        $customer = User::factory()->create();
        $token = $customer->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain);
        $this->bindTenant($venue->id);

        ['order' => $order] = $this->createPendingOrder($venue);

        $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'shamcash',
            'provider_transaction_id' => 'TXN-BLOCKED',
            'amount' => '120.00',
        ])->assertForbidden();
    }
}
