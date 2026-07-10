<?php

namespace App\Contracts\Notifications;

use App\Services\Notifications\Data\NotificationMessage;

/**
 * Delivers a rendered notification via a single transport (email, SMS, push, ...).
 *
 * Channels are invoked exclusively by {@see \App\Services\Notifications\NotificationDispatcher}.
 */
interface NotificationChannel
{
    public function channelKey(): string;

    public function send(NotificationMessage $message): void;
}
