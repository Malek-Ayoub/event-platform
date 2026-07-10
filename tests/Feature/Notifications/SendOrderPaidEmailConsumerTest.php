<?php

namespace Tests\Feature\Notifications;

use App\Contracts\Notifications\EmailSenderInterface;
use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Enums\OrdersDomain\OrderStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\PaymentTransaction;
use App\Models\Scopes\BelongsToVenueScope;
use App\Models\Ticket;
use App\Repositories\ConsumerReceiptRepository;
use App\Services\Outbox\OutboxDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Support\Notifications\RecordingEmailSender;
use Tests\TestCase;

class SendOrderPaidEmailConsumerTest extends TestCase
{
    use RefreshDatabase;

    private RecordingEmailSender $emailSender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailSender = new RecordingEmailSender;
        $this->app->instance(EmailSenderInterface::class, $this->emailSender);
    }

    #[Test]
    public function it_sends_one_email_when_processing_an_order_paid_outbox_event(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $outbox = $this->createOrderPaidOutboxEvent($venue);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertCount(1, $this->emailSender->sent);
        $this->assertSame(OutboxEventStatus::Sent, $outbox->fresh()->status);
        $this->assertTrue(
            app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'notification.email.order_paid'),
        );
    }

    #[Test]
    public function it_does_not_send_duplicate_emails_when_the_consumer_is_retried(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $outbox = $this->createOrderPaidOutboxEvent($venue);

        app(OutboxDispatcher::class)->dispatchPending();

        OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->whereKey($outbox->id)
            ->update(['status' => OutboxEventStatus::Pending, 'processed_at' => null]);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertCount(1, $this->emailSender->sent);
    }

    #[Test]
    public function it_uses_the_order_paid_template_with_expected_variables(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Damascus Jazz Night',
        ]);

        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'status' => OrderStatus::Paid,
            'customer_name' => 'Layla Hassan',
            'customer_email' => 'layla@example.com',
            'order_number' => 'ORD-EMAIL-001',
            'total' => '150.00',
        ]);

        Ticket::factory()->count(2)->create([
            'venue_id' => $venue->id,
            'order_id' => $order->id,
            'event_id' => $event->id,
        ]);

        PaymentTransaction::factory()->forOrder($order)->paid()->create([
            'venue_id' => $venue->id,
            'amount' => '150.00',
        ]);

        OutboxEvent::factory()->forVenue($venue)->forAggregate($order)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => [
                'payload' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'payment_transaction_id' => PaymentTransaction::query()->value('id'),
                ],
            ],
        ]);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertCount(1, $this->emailSender->sent);

        $email = $this->emailSender->sent[0];

        $this->assertSame('layla@example.com', $email['to']);
        $this->assertSame('Your tickets for Damascus Jazz Night', $email['subject']);
        $this->assertStringContainsString('Hello Layla Hassan', $email['body']);
        $this->assertStringContainsString('ORD-EMAIL-001', $email['body']);
        $this->assertStringContainsString('Tickets: 2', $email['body']);
        $this->assertStringContainsString('Total: 150.00', $email['body']);
        $this->assertStringContainsString('150.00', $email['body']);
        $this->assertStringContainsString('#pending-tickets/ORD-EMAIL-001', $email['body']);
        $this->assertSame('order.paid', $email['context']['template_slug']);
    }

    #[Test]
    public function it_schedules_retry_when_the_template_cannot_be_resolved(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        config(['notifications.templates' => []]);

        $outbox = $this->createOrderPaidOutboxEvent($venue);

        $result = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $result->failed);
        $this->assertSame(OutboxEventStatus::Pending, $outbox->fresh()->status);
        $this->assertSame(1, $outbox->fresh()->attempts);
        $this->assertCount(0, $this->emailSender->sent);
        $this->assertFalse(
            app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'notification.email.order_paid'),
        );
    }

    #[Test]
    public function it_schedules_retry_when_email_delivery_fails(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $this->emailSender->throwOnNextSend(new RuntimeException('email transport failed'));

        $outbox = $this->createOrderPaidOutboxEvent($venue);

        $result = app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(1, $result->failed);
        $this->assertSame(OutboxEventStatus::Pending, $outbox->fresh()->status);
        $this->assertSame(1, $outbox->fresh()->attempts);
        $this->assertFalse(
            app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'notification.email.order_paid'),
        );

        $this->emailSender->throwOnNextSend(new RuntimeException('still failing'));

        OutboxEvent::query()
            ->withoutGlobalScope(BelongsToVenueScope::class)
            ->whereKey($outbox->id)
            ->update(['updated_at' => now()->subHour()]);

        app(OutboxDispatcher::class)->dispatchPending();

        $this->assertSame(2, $outbox->fresh()->attempts);
        $this->assertFalse(
            app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'notification.email.order_paid'),
        );
        $this->assertTrue(
            app(ConsumerReceiptRepository::class)->hasProcessed($outbox->id, 'commission.order_paid'),
        );
    }

    /**
     * @return OutboxEvent
     */
    private function createOrderPaidOutboxEvent(\App\Models\Venue $venue): OutboxEvent
    {
        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Summer Fest',
        ]);

        $order = Order::factory()->forEvent($event)->create([
            'venue_id' => $venue->id,
            'status' => OrderStatus::Paid,
            'customer_name' => 'Alex Buyer',
            'customer_email' => 'alex@example.com',
            'order_number' => 'ORD-EMAIL-002',
            'total' => '120.00',
        ]);

        Ticket::factory()->create([
            'venue_id' => $venue->id,
            'order_id' => $order->id,
            'event_id' => $event->id,
        ]);

        PaymentTransaction::factory()->forOrder($order)->paid()->create([
            'venue_id' => $venue->id,
            'amount' => '120.00',
        ]);

        return OutboxEvent::factory()->forVenue($venue)->forAggregate($order)->create([
            'event_type' => 'order.paid',
            'status' => OutboxEventStatus::Pending,
            'payload' => [
                'payload' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total' => $order->total,
                    'payment_transaction_id' => PaymentTransaction::query()->value('id'),
                ],
            ],
        ]);
    }
}
