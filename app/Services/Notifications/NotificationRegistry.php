<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\NotificationChannel;
use App\Exceptions\Notifications\UnknownNotificationChannelException;

final class NotificationRegistry
{
    /** @var array<string, NotificationChannel> */
    private array $channelsByKey = [];

    public function register(NotificationChannel $channel): void
    {
        if ($channel->channelKey() === '') {
            throw new \InvalidArgumentException('Notification channel must define a non-empty channelKey().');
        }

        $this->channelsByKey[$channel->channelKey()] = $channel;
    }

    public function resolve(string $channelKey): NotificationChannel
    {
        return $this->channelsByKey[$channelKey]
            ?? throw UnknownNotificationChannelException::forChannel($channelKey);
    }

    /**
     * @return list<string>
     */
    public function registeredChannelKeys(): array
    {
        return array_keys($this->channelsByKey);
    }
}
