<?php

namespace Tests\Unit\Models\OrdersDomain;

use App\Enums\OrdersDomain\OrderStatus;
use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderDomainCastsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function order_casts_status_enum_and_decimal_amounts(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->paid()->create([
            'subtotal' => '100.00',
            'total' => '115.50',
        ]);

        $this->assertSame(OrderStatus::Paid, $order->status);
        $this->assertSame('100.00', $order->subtotal);
        $this->assertSame('115.50', $order->total);
    }

    #[Test]
    public function ticket_casts_status_enum(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();
        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'status' => TicketStatus::Issued,
            'issued_at' => now(),
        ]);

        $this->assertSame(TicketStatus::Issued, $ticket->status);
        $this->assertNotNull($ticket->issued_at);
    }
}
