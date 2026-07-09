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
use App\Services\Payments\Gateway\ShamCash\ShamCashGateway;
use App\Services\Payments\PaymentGatewayService;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

trait InteractsWithPaymentFlows
{
    protected function configureShamcashGateway(): void
    {
        $this->resetHttpFakes();

        config([
            'payment_gateways.providers.shamcash.base_url' => 'https://api.shamcash.test',
            'payment_gateways.providers.shamcash.api_key' => 'test-key',
            'payment_gateways.providers.shamcash.initiate_path' => '/v1/payments',
            'payment_gateways.providers.shamcash.refund_path' => '/v1/refunds',
            'payment_gateways.providers.shamcash.webhook_secret' => 'whsec_test',
        ]);
    }

    protected function resetHttpFakes(): void
    {
        Http::swap(new Factory);

        $this->app->forgetInstance(HttpClientInterface::class);
        $this->app->forgetInstance(LaravelHttpClientAdapter::class);
        $this->app->forgetInstance(ShamCashGateway::class);
        $this->app->forgetInstance(PaymentGatewayRegistry::class);
        $this->app->forgetInstance(PaymentGatewayService::class);
    }

    protected function fakeSuccessfulShamcashInitiate(
        string $transactionId,
        ?string $redirectUrl = null,
    ): void {
        Http::fake([
            'https://api.shamcash.test/v1/payments' => Http::response([
                'transaction_id' => $transactionId,
                'status' => 'pending',
                'redirect_url' => $redirectUrl ?? 'https://pay.shamcash.test/checkout/'.$transactionId,
            ], 201),
        ]);
    }

    protected function fakeDeclinedShamcashInitiate(string $message = 'Provider declined payment'): void
    {
        Http::fake([
            'https://api.shamcash.test/v1/payments' => Http::response([
                'transaction_id' => 'SC-TXN-DECLINED',
                'message' => $message,
            ], 422),
        ]);
    }

    protected function fakeSuccessfulShamcashRefund(string $refundId, string $status = 'pending'): void
    {
        Http::fake([
            'https://api.shamcash.test/v1/refunds' => Http::response([
                'refund_id' => $refundId,
                'status' => $status,
            ], 200),
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
     * @param  array<string, mixed>  $payload
     */
    protected function postSignedShamcashWebhook(array $payload): TestResponse
    {
        $rawBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $rawBody, 'whsec_test');

        return $this->postJson('/webhooks/shamcash', $payload, [
            'X-ShamCash-Signature' => $signature,
        ]);
    }
}
