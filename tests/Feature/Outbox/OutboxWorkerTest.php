<?php

namespace Tests\Feature\Outbox;

use App\Enums\FinancialDomain\CommissionStatus;
use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Commission;
use App\Models\OutboxEvent;
use App\Models\Scopes\BelongsToVenueScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\InteractsWithPaymentFlows;
use Tests\TestCase;

class OutboxWorkerTest extends TestCase
{
    use InteractsWithPaymentFlows;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
        $this->configureApiSyriaGateway();
    }

    #[Test]
    public function payment_flow_creates_pending_outbox_and_worker_records_commission(): void
    {
        ['token' => $token, 'venue' => $venue] = $this->authenticateVenueOwnerForPayments();
        $venue->update(['commission_rate' => 5.00]);
        ['order' => $order] = $this->createPendingOrderForPayments($venue);

        $transactionNumber = 'TX-OUTBOX-001';
        $this->fakeApiSyriaFindTx($transactionNumber);

        ['payment_id' => $paymentId] = $this->createPaymentInstructions($token, $order);
        $this->verifyPayment($token, $paymentId, $transactionNumber)->assertOk();

        $pendingOrderPaid = OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->where('event_type', 'order.paid')
            ->where('status', OutboxEventStatus::Pending)
            ->count();

        $this->assertSame(1, $pendingOrderPaid);
        $this->assertSame(0, Commission::query()->count());

        $this->artisan('outbox:process', ['--once' => true])->assertSuccessful();

        $this->assertSame(OrderStatus::Paid, $order->fresh()->status);
        $this->assertSame(1, Commission::query()->count());
        $this->assertSame('6.00', Commission::query()->value('amount'));
        $this->assertSame(CommissionStatus::Pending, Commission::query()->first()->status);

        $this->assertSame(
            0,
            OutboxEvent::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('event_type', 'order.paid')
                ->where('status', OutboxEventStatus::Pending)
                ->count(),
        );
    }

    #[Test]
    public function worker_processes_batches_until_queue_is_empty(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        OutboxEvent::factory()->forVenue($venue)->count(3)->create([
            'event_type' => 'order.created',
            'status' => OutboxEventStatus::Pending,
        ]);

        $this->artisan('outbox:process')->assertSuccessful();

        $this->assertSame(
            0,
            OutboxEvent::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('status', OutboxEventStatus::Pending)
                ->count(),
        );

        $this->assertSame(
            3,
            OutboxEvent::query()
                ->withoutGlobalScope(BelongsToVenueScope::class)
                ->where('status', OutboxEventStatus::Sent)
                ->count(),
        );
    }
}
