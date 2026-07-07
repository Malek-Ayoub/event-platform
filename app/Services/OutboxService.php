<?php

namespace App\Services;

use App\Enums\InfrastructureDomain\OutboxEventStatus;
use App\Models\OutboxEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OutboxService extends BaseService
{
    /**
     * Record an outbox event for async delivery. Must be called inside TransactionRunner::run().
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(
        string $eventType,
        object $aggregate,
        array $payload,
        ?int $venueId = null,
        int $version = 1,
    ): OutboxEvent {
        return OutboxEvent::query()->create([
            'venue_id' => $this->resolveVenueId($venueId, $aggregate),
            'event_type' => $eventType,
            'aggregate_type' => $this->aggregateType($aggregate),
            'aggregate_id' => $this->aggregateId($aggregate),
            'payload' => $this->envelope($eventType, $aggregate, $payload, $version),
            'status' => OutboxEventStatus::Pending,
            'attempts' => 0,
            'processed_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function envelope(string $eventType, object $aggregate, array $payload, int $version): array
    {
        return [
            'aggregate' => $this->aggregateShortName($aggregate),
            'aggregate_id' => $this->aggregateId($aggregate),
            'event' => $eventType,
            'version' => $version,
            'payload' => $payload,
        ];
    }

    private function aggregateShortName(object $aggregate): string
    {
        return Str::snake(class_basename($aggregate));
    }

    private function aggregateType(object $aggregate): string
    {
        return $aggregate::class;
    }

    private function aggregateId(object $aggregate): int
    {
        if ($aggregate instanceof Model) {
            return (int) $aggregate->getKey();
        }

        if (property_exists($aggregate, 'id')) {
            return (int) $aggregate->id;
        }

        throw new \InvalidArgumentException('Aggregate must be an Eloquent model or expose an id property.');
    }

    private function resolveVenueId(?int $venueId, object $aggregate): ?int
    {
        if ($venueId !== null) {
            return $venueId;
        }

        if ($aggregate instanceof Model && $aggregate->getAttribute('venue_id') !== null) {
            return (int) $aggregate->getAttribute('venue_id');
        }

        if ($this->tenantContext->isResolved()) {
            return $this->tenantContext->requireVenueId();
        }

        return null;
    }
}
