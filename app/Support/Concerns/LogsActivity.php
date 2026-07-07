<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Model;

trait LogsActivity
{
    /**
     * @return list<string>
     */
    abstract protected function getAuditableAttributes(): array;

    abstract protected function getActivityLogEntityType(): string;

    public static function bootLogsActivity(): void
    {
        static::updated(function (Model $model): void {
            if (! method_exists($model, 'getAuditableAttributes') || ! method_exists($model, 'getActivityLogEntityType')) {
                return;
            }

            /** @var self $model */
            $changes = $model->getChanges();
            $auditable = array_flip($model->getAuditableAttributes());
            $changedFields = array_values(array_intersect(array_keys($changes), array_keys($auditable)));

            if ($changedFields === []) {
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach ($changedFields as $field) {
                $oldValues[$field] = $model->getOriginal($field);
                $newValues[$field] = $model->getAttribute($field);
            }

            event(new \App\Events\ActivityLogged(
                entityType: $model->getActivityLogEntityType(),
                entityId: (int) $model->getKey(),
                venueId: $model->getAttribute('venue_id'),
                oldValues: $oldValues,
                newValues: $newValues,
                changedFields: $changedFields,
            ));
        });
    }
}
