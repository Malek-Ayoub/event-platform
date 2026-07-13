<?php

namespace Tests\Feature\Tickets;

use App\Enums\OrdersDomain\TicketStatus;
use App\Models\Event;
use App\Models\Order;
use App\Models\OutboxEvent;
use App\Models\Ticket;
use App\Models\TicketCheckIn;
use App\Models\TicketSnapshot;
use App\Models\TicketType;
use App\Models\User;
use App\Models\Venue;
use App\Services\Orders\QrTokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Concerns\SeedsPermissions;
use Tests\TestCase;

class TicketCheckInApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsPermissions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('tenancy.base_domain', 'localhost');
        $this->seedPermissionsCatalog();
    }

    #[Test]
    public function it_checks_in_a_ticket_via_qr_token(): void
    {
        ['token' => $token, 'ticket' => $ticket] = $this->authenticateStaffWithIssuedTicket();

        $response = $this->withToken($token)->postJson('/api/tenant/tickets/check-in', [
            'qr_token' => $ticket->qr_token,
            'gate_id' => 3,
            'device_id' => 'gate-a',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.ticket_number', 'EV000001-260801-000099')
            ->assertJsonPath('data.holder_name', 'Jane Doe')
            ->assertJsonPath('data.event_name', 'Gate Night')
            ->assertJsonPath('data.status', TicketStatus::CheckedIn->value)
            ->assertJsonMissingPath('data.qr_token')
            ->assertJsonMissingPath('data.serial');

        $this->assertSame(1, TicketCheckIn::query()->where('ticket_id', $ticket->id)->count());
        $this->assertSame(1, OutboxEvent::query()->where('event_type', 'ticket.checked_in')->count());
    }

    #[Test]
    public function it_returns_not_found_for_unknown_qr_tokens(): void
    {
        ['token' => $token] = $this->authenticateStaffWithIssuedTicket();

        $this->withToken($token)->postJson('/api/tenant/tickets/check-in', [
            'qr_token' => app(QrTokenGenerator::class)->generate(),
        ])->assertNotFound();
    }

    #[Test]
    public function it_rejects_a_second_scan_of_the_same_qr_token(): void
    {
        ['token' => $token, 'ticket' => $ticket] = $this->authenticateStaffWithIssuedTicket();

        $this->withToken($token)->postJson('/api/tenant/tickets/check-in', [
            'qr_token' => $ticket->qr_token,
        ])->assertOk();

        $this->withToken($token)->postJson('/api/tenant/tickets/check-in', [
            'qr_token' => $ticket->qr_token,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Ticket has already been checked in.');

        $this->assertSame(1, TicketCheckIn::query()->where('ticket_id', $ticket->id)->count());
    }

    /**
     * @return array{token: string, ticket: Ticket, venue: Venue, staff: User}
     */
    private function authenticateStaffWithIssuedTicket(): array
    {
        ['venue' => $venue] = $this->createVenueOwner();
        ['user' => $staff] = $this->createVenueStaff($venue);
        $token = $staff->createToken('api')->plainTextToken;

        $this->withTenantHost($venue->subdomain);
        $this->bindTenant($venue->id);

        $event = Event::factory()->create([
            'venue_id' => $venue->id,
            'name' => 'Gate Night',
            'end_datetime' => now()->addDay(),
        ]);

        $order = Order::factory()->forEvent($event)->create(['venue_id' => $venue->id]);
        $ticketType = TicketType::factory()->forEvent($event)->create();

        $ticket = Ticket::factory()->forOrder($order)->forTicketType($ticketType)->create([
            'venue_id' => $venue->id,
            'event_id' => $event->id,
            'ticket_number' => 'EV000001-260801-000099',
            'status' => TicketStatus::Issued,
        ]);

        TicketSnapshot::factory()->forTicket($ticket)->create([
            'payload' => [
                'event' => ['name' => 'Gate Night', 'starts_at' => now()->toIso8601String(), 'ends_at' => null],
                'venue' => ['name' => 'Main Hall'],
                'ticket_type' => ['name' => 'GA', 'color' => null],
                'holder' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
                'seat' => ['label' => null],
                'price' => ['amount' => '75.00', 'currency' => 'USD'],
                'ticket' => ['number' => $ticket->ticket_number, 'issued_at' => now()->toIso8601String()],
            ],
        ]);

        return ['token' => $token, 'ticket' => $ticket, 'venue' => $venue, 'staff' => $staff];
    }
}
