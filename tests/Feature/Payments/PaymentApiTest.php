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
use Illuminate\Support\Facades\Http;
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

    private function configureApiSyriaGateway(): void
    {
        config([
            'payment_gateways.providers.apisyria.base_url' => 'https://api.syria.test',
            'payment_gateways.providers.apisyria.api_key' => 'test-key',
            'payment_gateways.providers.apisyria.merchant_account' => 'WALLET-001',
            'payment_gateways.providers.apisyria.verify_transaction_path' => '/find_tx',
        ]);
    }

    #[Test]
    public function owner_can_create_instructions_verify_and_show_payment(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();
        ['order' => $order] = $this->createPendingOrder($venue);

        $this->configureApiSyriaGateway();

        Http::fake([
            'https://api.syria.test/find_tx' => Http::response([
                'found' => true,
                'transaction_id' => 'APISYRIA-TX-1001',
                'amount' => '120.00',
                'currency' => 'USD',
                'receiver_account' => 'WALLET-001',
                'status' => 'completed',
            ], 200),
        ]);

        $initiate = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => 'apisyria',
        ]);

        $initiate
            ->assertCreated()
            ->assertJsonPath('data.provider', 'apisyria')
            ->assertJsonPath('data.merchant_account', 'WALLET-001')
            ->assertJsonPath('data.amount', '120.00')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonStructure([
                'data' => [
                    'payment_id',
                    'provider',
                    'merchant_account',
                    'amount',
                    'currency',
                    'expires_at',
                    'instructions',
                ],
            ]);

        $paymentId = $initiate->json('data.payment_id');

        $this->withToken($token)->postJson("/api/tenant/payments/{$paymentId}/verify", [
            'transaction_number' => 'TX-1001',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Paid->value)
            ->assertJsonPath('data.transaction_number', 'TX-1001')
            ->assertJsonPath('data.provider_transaction_id', 'APISYRIA-TX-1001');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);

        $this->withToken($token)->getJson("/api/tenant/payments/{$paymentId}")
            ->assertOk()
            ->assertJsonPath('data.order_id', $order->id)
            ->assertJsonPath('data.status', PaymentTransactionStatus::Paid->value);
    }

    #[Test]
    public function owner_can_list_payments_with_filters(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();
        ['order' => $order] = $this->createPendingOrder($venue);

        PaymentTransaction::factory()->forOrder($order)->awaitingTransfer()->create([
            'provider' => 'apisyria',
            'amount' => '120.00',
        ]);

        $this->withToken($token)->getJson('/api/tenant/payments')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
                'links' => ['first', 'last', 'prev', 'next'],
            ]);

        $this->withToken($token)->getJson('/api/tenant/payments?order_id='.$order->id.'&status=awaiting_transfer')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->withToken($token)->getJson('/api/tenant/payments?status=paid')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function owner_can_fail_legacy_pending_payment(): void
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
    public function verify_rejects_duplicate_transaction_number(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwner();
        ['order' => $firstOrder] = $this->createPendingOrder($venue);
        ['order' => $secondOrder] = $this->createPendingOrder($venue);

        $this->configureApiSyriaGateway();

        Http::fake([
            'https://api.syria.test/find_tx' => Http::response([
                'found' => true,
                'transaction_id' => 'APISYRIA-TX-DUP-001',
                'amount' => '120.00',
                'currency' => 'USD',
                'receiver_account' => 'WALLET-001',
                'status' => 'completed',
            ], 200),
        ]);

        $firstPaymentId = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $firstOrder->id,
            'provider' => 'apisyria',
        ])->json('data.payment_id');

        $secondPaymentId = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $secondOrder->id,
            'provider' => 'apisyria',
        ])->json('data.payment_id');

        $this->withToken($token)->postJson("/api/tenant/payments/{$firstPaymentId}/verify", [
            'transaction_number' => 'TX-DUP-001',
        ])->assertOk();

        $this->withToken($token)->postJson("/api/tenant/payments/{$secondPaymentId}/verify", [
            'transaction_number' => 'TX-DUP-001',
        ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Transaction number [TX-DUP-001] has already been used for another payment.');
    }

    #[Test]
    public function initiating_payment_for_another_venues_order_fails_validation(): void
    {
        ['token' => $token] = $this->authenticateVenueOwner();

        ['venue' => $otherVenue] = $this->createVenueOwner();
        ['order' => $foreignOrder] = $this->createPendingOrder($otherVenue);

        $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $foreignOrder->id,
            'provider' => 'apisyria',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['order_id']);
    }

    #[Test]
    public function complete_payment_is_idempotent_for_legacy_pending_payments(): void
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
            'provider' => 'apisyria',
        ])->assertForbidden();
    }
}
