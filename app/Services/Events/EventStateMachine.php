<?php

namespace App\Services\Events;

use App\Enums\EventDomain\EventStatus;
use App\Exceptions\Events\InvalidEventStateTransitionException;

/**
 * Allowed transitions (schema-aligned):
 *
 * draft     → published, cancelled
 * published → cancelled, completed
 * cancelled → (terminal)
 * completed → (terminal)
 */
class EventStateMachine
{
    /** @var array<string, list<EventStatus>> */
    private const ALLOWED = [
        'draft' => [
            EventStatus::Published,
            EventStatus::Cancelled,
        ],
        'published' => [
            EventStatus::Cancelled,
            EventStatus::Completed,
        ],
        'cancelled' => [],
        'completed' => [],
    ];

    public function assertCanTransition(EventStatus $from, EventStatus $to): void
    {
        $allowed = self::ALLOWED[$from->value] ?? [];

        if (! in_array($to, $allowed, true)) {
            throw InvalidEventStateTransitionException::between($from, $to);
        }
    }

    public function canArchiveFrom(EventStatus $status): bool
    {
        return $status === EventStatus::Published || $status === EventStatus::Completed;
    }
}
