<?php

namespace App\Services\Notifications;

use App\Services\Notifications\Data\NotificationMessage;

/**
 * Routes notification messages to registered channels (Phase 8.2).
 *
 * Invoked from Outbox consumers — never from inside domain transactions.
 */
final class NotificationDispatcher
{
    public function __construct(
        private NotificationRegistry $registry,
    ) {}

    public function dispatch(NotificationMessage $message): void
    {
        $this->registry->resolve($message->channelKey)->send($message);
    }

    /**
     * @param  list<NotificationMessage>  $messages
     */
    public function dispatchMany(array $messages): void
    {
        foreach ($messages as $message) {
            $this->dispatch($message);
        }
    }
}
