<?php

namespace App\Events;

readonly class ActivityLogged
{
    /**
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  list<string>  $changedFields
     */
    public function __construct(
        public string $entityType,
        public int $entityId,
        public mixed $venueId,
        public array $oldValues,
        public array $newValues,
        public array $changedFields,
    ) {}
}
