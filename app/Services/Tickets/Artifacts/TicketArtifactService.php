<?php

namespace App\Services\Tickets\Artifacts;

use App\Enums\Tickets\TicketArtifactStatus;
use App\Enums\Tickets\TicketArtifactType;
use App\Models\Ticket;
use App\Models\TicketArtifact;

/**
 * Persists ticket artifact metadata (Phase 8.3.3b.1).
 */
final class TicketArtifactService
{
    public function record(
        Ticket $ticket,
        TicketArtifactType $type,
        string $disk,
        string $path,
        string $mimeType,
        string $binaryContents,
        ?int $version = null,
        TicketArtifactStatus $status = TicketArtifactStatus::Ready,
    ): TicketArtifact {
        $version ??= (int) config('tickets.artifact.default_version', 1);
        $checksum = hash('sha256', $binaryContents);

        return TicketArtifact::query()->updateOrCreate(
            [
                'ticket_id' => $ticket->id,
                'type' => $type,
                'version' => $version,
            ],
            [
                'status' => $status,
                'disk' => $disk,
                'path' => $path,
                'mime_type' => $mimeType,
                'checksum' => $checksum,
                'generated_at' => now(),
            ],
        );
    }

    public function markStatus(
        TicketArtifact $artifact,
        TicketArtifactStatus $status,
    ): TicketArtifact {
        $artifact->update(['status' => $status]);

        return $artifact->fresh();
    }

    public function findForTicket(
        Ticket $ticket,
        TicketArtifactType $type,
        ?int $version = null,
    ): ?TicketArtifact {
        $version ??= (int) config('tickets.artifact.default_version', 1);

        return TicketArtifact::query()
            ->where('ticket_id', $ticket->id)
            ->where('type', $type)
            ->where('version', $version)
            ->first();
    }

    public function findReadyForTicket(
        Ticket $ticket,
        TicketArtifactType $type,
        ?int $version = null,
    ): ?TicketArtifact {
        $artifact = $this->findForTicket($ticket, $type, $version);

        if ($artifact === null || ! $artifact->isReady()) {
            return null;
        }

        return $artifact;
    }
}
