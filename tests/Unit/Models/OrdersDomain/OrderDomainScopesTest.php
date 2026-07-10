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

class OrderDomainScopesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function order_status_scopes_filter_records(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        Order::factory()->forEvent($event)->create(['status' => OrderStatus::Pending]);
        Order::factory()->forEvent($event)->paid()->create();

        $this->assertCount(1, Order::query()->paid()->get());
        $this->assertCount(1, Order::query()->withStatus(OrderStatus::Pending)->get());
    }

    #[Test]
    public function ticket_status_scopes_filter_records(): void
    {
        ['venue' => $venue] = $this->createVenueOwner();
        $this->bindTenant($venue->id);

        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $order = Order::factory()->forEvent($event)->create();
        $ticketType = TicketType::factory()->forEvent($event)->create();

        Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'status' => TicketStatus::Issued,
        ]);
        Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'status' => TicketStatus::CheckedIn,
        ]);

        $this->assertCount(1, Ticket::query()->issued()->get());
        $this->assertCount(1, Ticket::query()->withStatus(TicketStatus::CheckedIn)->get());
    }
}
