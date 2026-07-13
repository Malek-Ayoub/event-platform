<?php

namespace Tests\Support\Concerns;

use App\Contracts\Payments\Http\HttpClientInterface;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\EventPaymentAccount;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Models\Venue;
use App\Services\Payments\Gateway\Http\Adapters\LaravelHttpClientAdapter;
use App\Services\Payments\Gateway\PaymentGatewayRegistry;
use App\Services\Payments\PaymentGatewayService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

trait InteractsWithPaymentFlows
{
    protected function configureApiSyriaGateway(): void
    {
        $this->resetHttpFakes();

        config([
            'payment_gateways.providers.apisyria.base_url' => 'https://api.syria.test',
            'payment_gateways.providers.apisyria.api_key' => 'test-key',
        ]);
    }

    /**
     * Attaches a default ShamCash wallet to an event (creates or reuses venue wallet).
     */
    protected function attachDefaultPaymentAccount(
        Event $event,
        string $accountIdentifier = 'WALLET-001',
    ): PaymentAccount {
        $account = PaymentAccount::query()->firstOrCreate(
            [
                'venue_id' => $event->venue_id,
                'provider' => 'shamcash',
                'account_identifier' => $accountIdentifier,
            ],
            [
                'display_name' => $event->name.' ShamCash',
                'currency' => 'USD',
            ],
        );

        EventPaymentAccount::query()->firstOrCreate(
            [
                'event_id' => $event->id,
                'payment_account_id' => $account->id,
            ],
            [
                'is_default' => true,
                'is_active' => true,
            ],
        );

        return $account;
    }

    protected function resetHttpFakes(): void
    {
        Http::swap(new Factory);

        $this->app->forgetInstance(HttpClientInterface::class);
        $this->app->forgetInstance(LaravelHttpClientAdapter::class);
        $this->app->forgetInstance(PaymentGatewayRegistry::class);
        $this->app->forgetInstance(PaymentGatewayService::class);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function fakeApiSyriaFindTx(
        string $transactionNumber,
        array $overrides = [],
        int $status = 200,
        string $merchantAccount = 'WALLET-001',
    ): void {
        $defaults = [
            'tran_id' => 'APISYRIA-'.$transactionNumber,
            'currency' => 'USD',
            'amount' => '120.00',
            'account' => $merchantAccount,
        ];

        $found = (bool) ($overrides['found'] ?? true);
        $transactionOverrides = array_diff_key($overrides, ['found' => true]);
        $transaction = array_merge($defaults, $transactionOverrides);

        Http::fake([
            'https://api.syria.test*' => Http::response([
                'success' => true,
                'data' => [
                    'found' => $found,
                    'transaction' => $found ? $transaction : null,
                ],
            ], $status),
        ]);
    }

    protected function fakeApiSyriaFindTxNotFound(string $transactionNumber): void
    {
        $this->fakeApiSyriaFindTx($transactionNumber, ['found' => false]);
    }

    /**
     * @return array{owner: User, venue: Venue, token: string}
     */
    protected function authenticateVenueOwnerForPayments(): array
    {
        $this->seedPermissionsCatalog();
        ['user' => $owner, 'venue' => $venue] = $this->createVenueOwner();
        $token = $owner->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain);
        $this->bindTenant($venue->id);

        return ['owner' => $owner, 'venue' => $venue, 'token' => $token];
    }

    /**
     * Creates a pending order for payments, and ensures the event has a default
     * payment account so OrderService / PaymentAccountResolver can resolve it.
     *
     * @return array{order: Order, event: Event}
     */
    protected function createPendingOrderForPayments(Venue $venue, string $total = '120.00'): array
    {
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $account = $this->attachDefaultPaymentAccount($event);

        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'payment_account_id' => $account->id,
            'total' => $total,
            'subtotal' => $total,
            'status' => OrderStatus::Pending,
        ]);

        return ['order' => $order, 'event' => $event];
    }

    /**
     * @return array{payment_id: int, response: TestResponse}
     */
    protected function createPaymentInstructions(string $token, Order $order, string $provider = 'apisyria'): array
    {
        $response = $this->withToken($token)->postJson('/api/tenant/payments', [
            'order_id' => $order->id,
            'provider' => $provider,
        ])->assertCreated();

        return [
            'payment_id' => (int) $response->json('data.payment_id'),
            'response' => $response,
        ];
    }

    protected function verifyPayment(
        string $token,
        int $paymentId,
        string $transactionNumber,
    ): TestResponse {
        return $this->withToken($token)->postJson("/api/tenant/payments/{$paymentId}/verify", [
            'transaction_number' => $transactionNumber,
        ]);
    }
}
