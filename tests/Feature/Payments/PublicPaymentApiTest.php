<?php

namespace Tests\Feature\Payments;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;

class PublicPaymentApiTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
        Cache::flush();
    }

    /**
     * @return array{venue: Venue, order: Order}
     */
    private function seedPendingGuestOrder(string $subdomain = 'guest-pay'): array
    {
        $venue = Venue::factory()->create(['subdomain' => $subdomain]);
        $this->bindTenant($venue->id);

        ['order' => $order] = $this->createPendingOrderForPayments($venue, '120.00');

        return compact('venue', 'order');
    }

    #[Test]
    public function it_creates_payment_instructions_for_a_pending_guest_order(): void
    {
        ['venue' => $venue, 'order' => $order] = $this->seedPendingGuestOrder();

        $response = $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-instructions");

        $response
            ->assertCreated()
            ->assertJsonPath('data.provider', 'shamcash')
            ->assertJsonPath('data.merchant_account', 'WALLET-001')
            ->assertJsonPath('data.amount', '120.00')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonStructure([
                'data' => [
                    'provider',
                    'merchant_account',
                    'amount',
                    'currency',
                    'expires_at',
                    'instructions',
                ],
            ])
            ->assertJsonMissingPath('data.payment_id')
            ->assertJsonMissingPath('data.payment_account_id');

        $this->assertDatabaseHas('payment_transactions', [
            'order_id' => $order->id,
            'provider' => 'apisyria',
            'status' => PaymentTransactionStatus::AwaitingTransfer->value,
            'amount' => '120.00',
        ]);
    }

    #[Test]
    public function it_returns_the_same_instructions_when_an_active_awaiting_transfer_already_exists(): void
    {
        ['venue' => $venue, 'order' => $order] = $this->seedPendingGuestOrder('guest-pay-idem');

        $first = $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-instructions")
            ->assertCreated();

        $second = $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-instructions")
            ->assertCreated();

        $this->assertSame($first->json('data.merchant_account'), $second->json('data.merchant_account'));
        $this->assertSame($first->json('data.amount'), $second->json('data.amount'));
        $this->assertSame($first->json('data.expires_at'), $second->json('data.expires_at'));
        $this->assertSame(
            1,
            PaymentTransaction::query()->where('order_id', $order->id)->count(),
        );
    }

    #[Test]
    public function it_returns_404_for_unknown_or_non_pending_orders(): void
    {
        ['venue' => $venue, 'order' => $order] = $this->seedPendingGuestOrder('guest-pay-404');

        $this->withTenantHost($venue->subdomain)
            ->postJson('/api/public/orders/ORD-DOESNOTEXIST/payment-instructions')
            ->assertNotFound();

        $order->update(['status' => OrderStatus::Paid]);
        $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-instructions")
            ->assertNotFound();

        $order->update(['status' => OrderStatus::Cancelled]);
        $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-instructions")
            ->assertNotFound();
    }

    #[Test]
    public function it_does_not_issue_instructions_for_another_tenants_order(): void
    {
        $venueA = Venue::factory()->create(['subdomain' => 'pay-tenant-a']);
        $venueB = Venue::factory()->create(['subdomain' => 'pay-tenant-b']);

        $this->bindTenant($venueB->id);
        ['order' => $orderB] = $this->createPendingOrderForPayments($venueB);

        $this->withTenantHost($venueA->subdomain)
            ->postJson("/api/public/orders/{$orderB->order_number}/payment-instructions")
            ->assertNotFound();
    }

    #[Test]
    public function it_verifies_a_matching_transaction_number_as_paid(): void
    {
        ['venue' => $venue, 'order' => $order] = $this->seedPendingGuestOrder('guest-verify-ok');

        $this->configureApiSyriaGateway();
        $this->fakeApiSyriaFindTx('TX-GUEST-001', [
            'tran_id' => 'APISYRIA-TX-GUEST-001',
            'amount' => '120.00',
        ]);

        $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-instructions")
            ->assertCreated();

        $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-verification", [
                'transaction_number' => 'TX-GUEST-001',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Paid->value)
            ->assertJsonPath('data.message', 'Payment confirmed.')
            ->assertJsonMissingPath('data.id')
            ->assertJsonMissingPath('data.payment_id');

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
    }

    #[Test]
    public function it_marks_verification_as_failed_when_the_gateway_does_not_match(): void
    {
        ['venue' => $venue, 'order' => $order] = $this->seedPendingGuestOrder('guest-verify-fail');

        $this->configureApiSyriaGateway();
        $this->fakeApiSyriaFindTxNotFound('TX-GUEST-MISSING');

        $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-instructions")
            ->assertCreated();

        $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-verification", [
                'transaction_number' => 'TX-GUEST-MISSING',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PaymentTransactionStatus::Failed->value)
            ->assertJsonPath('data.message', 'Payment verification failed.');

        $this->assertSame(OrderStatus::Pending, $order->fresh()->status);
    }

    #[Test]
    public function it_returns_404_when_verifying_without_an_active_payment_instruction(): void
    {
        ['venue' => $venue, 'order' => $order] = $this->seedPendingGuestOrder('guest-verify-none');

        $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-verification", [
                'transaction_number' => 'TX-NONE',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function it_rate_limits_payment_instruction_requests(): void
    {
        ['venue' => $venue, 'order' => $order] = $this->seedPendingGuestOrder('guest-throttle-inst');

        for ($i = 0; $i < 10; $i++) {
            $this->withTenantHost($venue->subdomain)
                ->postJson("/api/public/orders/{$order->order_number}/payment-instructions")
                ->assertCreated();
        }

        $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-instructions")
            ->assertStatus(429);
    }

    #[Test]
    public function it_rate_limits_payment_verification_requests(): void
    {
        ['venue' => $venue, 'order' => $order] = $this->seedPendingGuestOrder('guest-throttle-verify');

        for ($i = 0; $i < 10; $i++) {
            $this->withTenantHost($venue->subdomain)
                ->postJson("/api/public/orders/{$order->order_number}/payment-verification", [
                    'transaction_number' => 'TX-THROTTLE-'.$i,
                ])
                ->assertNotFound();
        }

        $this->withTenantHost($venue->subdomain)
            ->postJson("/api/public/orders/{$order->order_number}/payment-verification", [
                'transaction_number' => 'TX-THROTTLE-FINAL',
            ])
            ->assertStatus(429);
    }
}
