<?php

namespace Tests\Feature\Webhooks;

use App\Enums\FinancialDomain\PaymentTransactionStatus;
use App\Enums\InfrastructureDomain\WebhookLogStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\WebhookLog;
use App\Support\Webhooks\WebhookCorrelation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebhookApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function webhook_completes_payment_with_valid_signature(): void
    {
        config(['payment_gateways.providers.shamcash.webhook_secret' => 'whsec_test']);

        ['payment' => $payment, 'order' => $order] = $this->createPendingPayment('shamcash', 'TXN-WH-001');

        $payload = [
            'event_id' => 'evt_complete_1',
            'event_type' => 'payment.completed',
            'provider_transaction_id' => 'TXN-WH-001',
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawBody, 'whsec_test');

        $response = $this->postJson('/webhooks/shamcash', $payload, [
            'X-ShamCash-Signature' => $signature,
        ]);

        $response
            ->assertAccepted()
            ->assertJsonPath('data.status', WebhookLogStatus::Processed->value)
            ->assertJsonPath('data.duplicate', false);

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(PaymentTransactionStatus::Completed, $payment->fresh()->status);

        $this->assertDatabaseHas('webhook_logs', [
            'provider' => 'shamcash',
            'provider_event_id' => 'evt_complete_1',
            'correlation_id' => WebhookCorrelation::id('shamcash', 'evt_complete_1'),
            'status' => WebhookLogStatus::Processed->value,
        ]);

        $correlationId = WebhookCorrelation::id('shamcash', 'evt_complete_1');

        $this->assertDatabaseHas('activity_logs', [
            'correlation_id' => $correlationId,
        ]);

        $this->assertDatabaseHas('outbox_events', [
            'correlation_id' => $correlationId,
        ]);
    }

    #[Test]
    public function duplicate_webhook_is_marked_replayed(): void
    {
        config(['payment_gateways.providers.shamcash.webhook_secret' => 'whsec_test']);

        WebhookLog::factory()->create([
            'provider' => 'shamcash',
            'provider_event_id' => 'evt_dup_1',
            'status' => WebhookLogStatus::Processed,
            'payload' => '{}',
        ]);

        $payload = [
            'event_id' => 'evt_dup_1',
            'event_type' => 'payment.completed',
            'provider_transaction_id' => 'TXN-DUP',
        ];
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawBody, 'whsec_test');

        $this->postJson('/webhooks/shamcash', $payload, [
            'X-ShamCash-Signature' => $signature,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', WebhookLogStatus::Replayed->value)
            ->assertJsonPath('data.duplicate', true);
    }

    #[Test]
    public function invalid_signature_returns_unauthorized_and_logs_failed_signature(): void
    {
        config(['payment_gateways.providers.shamcash.webhook_secret' => 'whsec_test']);

        $payload = [
            'event_id' => 'evt_bad_sig',
            'event_type' => 'payment.completed',
            'provider_transaction_id' => 'TXN-BAD',
        ];

        $this->postJson('/webhooks/shamcash', $payload, [
            'X-ShamCash-Signature' => 'invalid',
        ])
            ->assertUnauthorized();

        $this->assertDatabaseHas('webhook_logs', [
            'provider' => 'shamcash',
            'provider_event_id' => 'evt_bad_sig',
            'status' => WebhookLogStatus::FailedSignature->value,
        ]);
    }

    #[Test]
    public function unknown_provider_returns_unauthorized(): void
    {
        $payload = ['event_id' => 'evt_unknown', 'event_type' => 'payment.completed'];

        $this->postJson('/webhooks/unknown_provider', $payload)
            ->assertUnauthorized();
    }

    /**
     * @return array{payment: PaymentTransaction, order: Order}
     */
    private function createPendingPayment(string $provider, string $providerTransactionId): array
    {
        $this->seedPermissionsCatalog();
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'total' => '120.00',
            'subtotal' => '120.00',
            'status' => OrderStatus::Pending,
        ]);

        $payment = PaymentTransaction::factory()->forOrder($order)->create([
            'venue_id' => $venue->id,
            'provider' => $provider,
            'provider_transaction_id' => $providerTransactionId,
            'amount' => '120.00',
            'status' => PaymentTransactionStatus::Pending,
        ]);

        return ['payment' => $payment, 'order' => $order];
    }
}
