<?php

namespace Database\Factories;

use App\Enums\Tickets\TicketArtifactStatus;
use App\Enums\Tickets\TicketArtifactType;
use App\Models\Ticket;
use App\Models\TicketArtifact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TicketArtifact>
 */
class TicketArtifactFactory extends Factory
{
    protected $model = TicketArtifact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'type' => TicketArtifactType::Qr,
            'version' => 1,
            'status' => TicketArtifactStatus::Ready,
            'disk' => 'local',
            'path' => 'tickets/qr/'.fake()->uuid().'.png',
            'mime_type' => 'image/png',
            'checksum' => hash('sha256', 'test'),
            'generated_at' => now(),
        ];
    }

    public function forTicket(Ticket $ticket): static
    {
        return $this->state(fn (array $attributes) => [
            'ticket_id' => $ticket->id,
        ]);
    }
}
