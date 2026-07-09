<?php

namespace Tests\Support\Concerns;

use App\Contracts\Payments\Http\HttpClientInterface;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
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
            'payment_gateways.providers.apisyria.merchant_account' => 'WALLET-001',
            'payment_gateways.providers.apisyria.verify_transaction_path' => '/find_tx',
        ]);
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
    ): void {
        Http::fake([
            'https://api.syria.test/find_tx' => Http::response(array_merge([
                'found' => true,
                'transaction_id' => 'APISYRIA-'.$transactionNumber,
                'amount' => '120.00',
                'currency' => 'USD',
                'receiver_account' => 'WALLET-001',
                'status' => 'completed',
            ], $overrides), $status),
        ]);
    }

    protected function fakeApiSyriaFindTxNotFound(string $transactionNumber): void
    {
        $this->fakeApiSyriaFindTx($transactionNumber, [
            'found' => false,
            'transaction_id' => null,
            'amount' => null,
            'currency' => null,
            'receiver_account' => null,
        ]);
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
     * @return array{order: Order, event: Event}
     */
    protected function createPendingOrderForPayments(Venue $venue, string $total = '120.00'): array
    {
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
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
