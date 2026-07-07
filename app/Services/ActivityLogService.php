<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogService extends BaseService
{
    /**
     * Append an audit log entry. Must be called inside TransactionRunner::run().
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  list<string>|null  $changedFields
     */
    public function record(
        ?User $actor,
        object $entity,
        string $action,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $changedFields = null,
        ?string $ipAddress = null,
        ?int $venueId = null,
    ): ActivityLog {
        return ActivityLog::query()->create([
            'venue_id' => $this->resolveVenueId($venueId, $entity),
            'actor_user_id' => $actor?->id,
            'entity_type' => $this->entityType($entity),
            'entity_id' => $this->entityId($entity),
            'action' => $action,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'ip_address' => $ipAddress,
        ]);
    }

    private function entityType(object $entity): string
    {
        return $entity::class;
    }

    private function entityId(object $entity): int
    {
        if ($entity instanceof Model) {
            return (int) $entity->getKey();
        }

        if (property_exists($entity, 'id')) {
            return (int) $entity->id;
        }

        throw new \InvalidArgumentException('Entity must be an Eloquent model or expose an id property.');
    }

    private function resolveVenueId(?int $venueId, object $entity): ?int
    {
        if ($venueId !== null) {
            return $venueId;
        }

        if ($entity instanceof Model && $entity->getAttribute('venue_id') !== null) {
            return (int) $entity->getAttribute('venue_id');
        }

        if ($this->tenantContext->isResolved()) {
            return $this->tenantContext->requireVenueId();
        }

        return null;
    }
}
